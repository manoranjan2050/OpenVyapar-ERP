#![windows_subsystem = "windows"]

use std::ffi::OsStr;
use std::mem::{self, zeroed};
use std::os::windows::ffi::OsStrExt;
use std::os::windows::process::CommandExt;
use std::path::PathBuf;
use std::process::{Child, Command};
use std::ptr;
use std::sync::{Mutex, OnceLock};
use std::time::{SystemTime, UNIX_EPOCH};

use winapi::shared::minwindef::*;
use winapi::shared::windef::*;
use winapi::um::commctrl::*;
use winapi::um::libloaderapi::*;
use winapi::um::shellapi::*;
use winapi::um::wingdi::*;
use winapi::um::winuser::*;

// ── Send-safe wrappers for Win32 handles ─────────────────────────────────────

macro_rules! sendable {
    ($name:ident, $inner:ty) => {
        struct $name($inner);
        unsafe impl Send for $name {}
        unsafe impl Sync for $name {}
    };
}
sendable!(SHInstance, HINSTANCE);
sendable!(SHBrush,    HBRUSH);
sendable!(SHIcon,     HICON);

static HINST:     OnceLock<SHInstance> = OnceLock::new();
static HDR_BRUSH: OnceLock<SHBrush>   = OnceLock::new();
static APP_ICON:  OnceLock<SHIcon>    = OnceLock::new();

// ── Ports / IDs / timers ─────────────────────────────────────────────────────

const PORT_API: u16 = 8000;
const PORT_UI:  u16 = 8080;
const CREATE_NO_WINDOW: u32 = 0x0800_0000;
const WM_TRAYICON: UINT = WM_USER + 1;
const TRAY_UID: UINT = 42;
const TIMER_BROWSER: usize = 1;

const ID_BTN_START:   u16 = 101;
const ID_BTN_STOP:    u16 = 102;
const ID_BTN_RESTART: u16 = 103;
const ID_BTN_BROWSER: u16 = 104;
const ID_BTN_BACKUP:  u16 = 105;
const ID_BTN_TRAY:    u16 = 106;
const ID_LISTBOX:     u16 = 110;
const ID_LBL_STATUS:  u16 = 111;
const ID_HEADER:      u16 = 112;

// ── Button colour + icon config ───────────────────────────────────────────────

struct BtnCfg {
    id:    u16,
    label: &'static str,
    icon:  &'static str,   // Segoe MDL2 Assets codepoint
    r: u8, g: u8, b: u8,  // Base colour
}

const BTNS: &[BtnCfg] = &[
    // Row 1
    BtnCfg { id: ID_BTN_START,   label: "Start Servers",    icon: "\u{E768}", r: 46,  g: 125, b: 50  }, // green
    BtnCfg { id: ID_BTN_STOP,    label: "Stop Servers",     icon: "\u{E71A}", r: 198, g: 40,  b: 40  }, // red
    BtnCfg { id: ID_BTN_RESTART, label: "Restart",          icon: "\u{E72C}", r: 230, g: 81,  b: 0   }, // orange
    // Row 2
    BtnCfg { id: ID_BTN_BROWSER, label: "Open Browser",     icon: "\u{E774}", r: 21,  g: 101, b: 192 }, // blue
    BtnCfg { id: ID_BTN_BACKUP,  label: "Backup Now",       icon: "\u{E74E}", r: 106, g: 27,  b: 154 }, // purple
    BtnCfg { id: ID_BTN_TRAY,    label: "Hide to Tray",     icon: "\u{E70D}", r: 62,  g: 80,  b: 95  }, // slate
];

fn btn_color(cfg: &BtnCfg, pressed: bool, disabled: bool) -> COLORREF {
    if disabled { return unsafe { RGB(150, 150, 150) }; }
    let f = if pressed { 0.72f32 } else { 1.0 };
    unsafe { RGB((cfg.r as f32 * f) as u8, (cfg.g as f32 * f) as u8, (cfg.b as f32 * f) as u8) }
}

// ── App state ─────────────────────────────────────────────────────────────────

struct AppState { api_proc: Option<Child>, ui_proc: Option<Child>, running: bool }
static STATE: OnceLock<Mutex<AppState>> = OnceLock::new();

fn is_running() -> bool { STATE.get().map(|m| m.lock().unwrap().running).unwrap_or(false) }

// ── Utilities ─────────────────────────────────────────────────────────────────

fn wstr(s: &str) -> Vec<u16> { OsStr::new(s).encode_wide().chain(std::iter::once(0)).collect() }
fn loword(x: WPARAM) -> u16  { (x & 0xFFFF) as u16 }
fn hm(id: u16)     -> HMENU  { id as usize as HMENU }
fn root_dir()      -> PathBuf { std::env::current_exe().unwrap().parent().unwrap().to_path_buf() }
fn font_normal()   -> i32     { 400 }
fn font_semibold() -> i32     { 600 }
fn font_bold()     -> i32     { 700 }

fn ts() -> String {
    let s = SystemTime::now().duration_since(UNIX_EPOCH).unwrap_or_default().as_secs();
    format!("{:02}:{:02}:{:02}", (s / 3600) % 24, (s / 60) % 60, s % 60)
}

unsafe fn make_font(size: i32, weight: i32, name: &str) -> HFONT {
    CreateFontW(size, 0, 0, 0, weight, 0, 0, 0,
        DEFAULT_CHARSET as u32, OUT_DEFAULT_PRECIS as u32,
        CLIP_DEFAULT_PRECIS as u32, CLEARTYPE_QUALITY as u32,
        DEFAULT_PITCH as u32, wstr(name).as_ptr())
}

// ── Custom app icon (32×32, navy "OV") ───────────────────────────────────────

unsafe fn create_icon() -> HICON {
    let sdc = GetDC(ptr::null_mut());
    let mdc = CreateCompatibleDC(sdc);
    let bmp = CreateCompatibleBitmap(sdc, 32, 32);
    ReleaseDC(ptr::null_mut(), sdc);
    let old = SelectObject(mdc, bmp as *mut _);

    // Navy background
    let bg = CreateSolidBrush(RGB(26, 35, 126));
    let r  = RECT { left: 0, top: 0, right: 32, bottom: 32 };
    FillRect(mdc, &r, bg);
    DeleteObject(bg as *mut _);

    // White "OV" text
    SetBkMode(mdc, TRANSPARENT as i32);
    SetTextColor(mdc, RGB(255, 255, 255));
    let f = make_font(15, font_bold(), "Segoe UI");
    let of = SelectObject(mdc, f as *mut _);
    let t = wstr("OV");
    let mut rr = r;
    DrawTextW(mdc, t.as_ptr(), -1, &mut rr, DT_CENTER | DT_VCENTER | DT_SINGLELINE);
    SelectObject(mdc, of);
    SelectObject(mdc, old);
    DeleteObject(f as *mut _);
    DeleteDC(mdc);

    let mask = CreateBitmap(32, 32, 1, 1, ptr::null());
    let ii   = ICONINFO { fIcon: TRUE as BOOL, xHotspot: 0, yHotspot: 0, hbmMask: mask, hbmColor: bmp };
    let icon = CreateIconIndirect(&ii as *const _ as *mut _);
    DeleteObject(mask as *mut _);
    DeleteObject(bmp  as *mut _);
    if icon.is_null() { LoadIconW(ptr::null_mut(), IDI_APPLICATION) } else { icon }
}

// ── Log list-box ─────────────────────────────────────────────────────────────

fn log_msg(hwnd: HWND, msg: &str) {
    unsafe {
        let lb   = GetDlgItem(hwnd, ID_LISTBOX as i32);
        let line = wstr(&format!("[{}]  {}", ts(), msg));
        let idx  = SendMessageW(lb, LB_ADDSTRING, 0, line.as_ptr() as LPARAM);
        let cnt  = SendMessageW(lb, LB_GETCOUNT, 0, 0);
        if cnt > 300 { SendMessageW(lb, LB_DELETESTRING, 0, 0); }
        SendMessageW(lb, LB_SETCURSEL, idx as WPARAM, 0);
    }
}

// ── Server control ────────────────────────────────────────────────────────────

fn do_start(hwnd: HWND) {
    let root = root_dir();
    let php  = root.join("php").join("php.exe");
    let app  = root.join("app");
    let www  = root.join("www");

    if !php.exists() {
        log_msg(hwnd, "ERROR: php\\php.exe not found. Package may be incomplete.");
        return;
    }

    log_msg(hwnd, &format!("Starting API server  ->  http://localhost:{}", PORT_API));
    let api = Command::new(&php)
        .args(["-S", &format!("localhost:{}", PORT_API), "-t", "public", "public\\index.php"])
        .current_dir(&app)
        .stdin(std::process::Stdio::null()).stdout(std::process::Stdio::null()).stderr(std::process::Stdio::null())
        .creation_flags(CREATE_NO_WINDOW).spawn();

    log_msg(hwnd, &format!("Starting UI  server  ->  http://localhost:{}", PORT_UI));
    let ui = Command::new(&php)
        .args(["-S", &format!("localhost:{}", PORT_UI), "-t", www.to_str().unwrap_or("www")])
        .current_dir(&www)
        .stdin(std::process::Stdio::null()).stdout(std::process::Stdio::null()).stderr(std::process::Stdio::null())
        .creation_flags(CREATE_NO_WINDOW).spawn();

    let ok = api.is_ok() && ui.is_ok();
    if let Some(m) = STATE.get() {
        let mut s = m.lock().unwrap();
        s.api_proc = api.ok(); s.ui_proc = ui.ok(); s.running = ok;
    }
    if ok {
        log_msg(hwnd, "Servers started. Opening browser in 3 seconds...");
        unsafe { SetTimer(hwnd, TIMER_BROWSER, 3000, None); }
    } else {
        log_msg(hwnd, "ERROR: Could not start servers. Check php\\ folder.");
    }
    update_ui(hwnd);
}

fn do_stop(hwnd: HWND) {
    if let Some(m) = STATE.get() {
        let mut s = m.lock().unwrap();
        if let Some(mut p) = s.api_proc.take() { let _ = p.kill(); }
        if let Some(mut p) = s.ui_proc.take()  { let _ = p.kill(); }
        s.running = false;
    }
    log_msg(hwnd, "Servers stopped.");
    update_ui(hwnd);
}

fn do_restart(hwnd: HWND) {
    do_stop(hwnd);
    log_msg(hwnd, "Restarting...");
    do_start(hwnd);
}

fn do_backup(hwnd: HWND) {
    let root = root_dir();
    let src  = root.join("app").join("database").join("openvyapar.sqlite");
    if !src.exists() { log_msg(hwnd, "Backup failed: database file not found."); return; }
    let dir  = root.join("backups");
    let _    = std::fs::create_dir_all(&dir);
    let ts_v = SystemTime::now().duration_since(UNIX_EPOCH).unwrap_or_default().as_secs();
    let name = format!("backup_{}.sqlite", ts_v);
    match std::fs::copy(&src, dir.join(&name)) {
        Ok(_)  => log_msg(hwnd, &format!("Backup saved  ->  backups\\{}", name)),
        Err(e) => log_msg(hwnd, &format!("Backup failed: {}", e)),
    }
}

// ── UI refresh ────────────────────────────────────────────────────────────────

fn update_ui(hwnd: HWND) {
    unsafe {
        let running = is_running();
        let label   = if running { "  \u{25CF}  Running" } else { "  \u{25CF}  Stopped" };
        SetWindowTextW(GetDlgItem(hwnd, ID_LBL_STATUS as i32), wstr(label).as_ptr());
        InvalidateRect(GetDlgItem(hwnd, ID_LBL_STATUS as i32), ptr::null(), TRUE);

        for (id, enable) in [
            (ID_BTN_START,   !running),
            (ID_BTN_STOP,     running),
            (ID_BTN_RESTART,  running),
        ] {
            let btn = GetDlgItem(hwnd, id as i32);
            EnableWindow(btn, if enable { TRUE } else { FALSE });
            InvalidateRect(btn, ptr::null(), TRUE);
        }
    }
}

// ── Owner-draw button painter ─────────────────────────────────────────────────

unsafe fn draw_button(dis: *const DRAWITEMSTRUCT) {
    let dis      = &*dis;
    let id       = dis.CtlID as u16;
    let hdc      = dis.hDC;
    let rect     = dis.rcItem;
    let pressed  = (dis.itemState & ODS_SELECTED) != 0;
    let disabled = (dis.itemState & ODS_DISABLED) != 0;

    let cfg = match BTNS.iter().find(|b| b.id == id) { Some(c) => c, None => return };
    let color = btn_color(cfg, pressed, disabled);

    // — Rounded rectangle background —
    let brush   = CreateSolidBrush(color);
    let old_b   = SelectObject(hdc, brush as *mut _);
    let null_p  = GetStockObject(NULL_PEN as i32);
    let old_p   = SelectObject(hdc, null_p);
    RoundRect(hdc, rect.left, rect.top, rect.right + 1, rect.bottom + 1, 10, 10);
    SelectObject(hdc, old_b);
    SelectObject(hdc, old_p);
    DeleteObject(brush as *mut _);

    // — Text colours —
    let tc = if disabled { RGB(210, 210, 210) } else { RGB(255, 255, 255) };
    SetTextColor(hdc, tc);
    SetBkMode(hdc, TRANSPARENT as i32);

    // — Icon via Segoe MDL2 Assets —
    let icon_f  = make_font(18, font_normal(), "Segoe MDL2 Assets");
    let old_if  = SelectObject(hdc, icon_f as *mut _);
    let icon    = wstr(cfg.icon);
    let mut ir  = RECT { left: rect.left + 6, top: rect.top, right: rect.left + 32, bottom: rect.bottom };
    DrawTextW(hdc, icon.as_ptr(), -1, &mut ir, DT_CENTER | DT_VCENTER | DT_SINGLELINE);
    SelectObject(hdc, old_if);
    DeleteObject(icon_f as *mut _);

    // — Label via Segoe UI —
    let lbl_f  = make_font(13, font_semibold(), "Segoe UI");
    let old_lf = SelectObject(hdc, lbl_f as *mut _);
    let label  = wstr(cfg.label);
    let mut lr = RECT { left: rect.left + 32, top: rect.top, right: rect.right - 4, bottom: rect.bottom };
    DrawTextW(hdc, label.as_ptr(), -1, &mut lr, DT_LEFT | DT_VCENTER | DT_SINGLELINE);
    SelectObject(hdc, old_lf);
    DeleteObject(lbl_f as *mut _);

    // — Focus rect —
    if (dis.itemState & ODS_FOCUS) != 0 { DrawFocusRect(hdc, &dis.rcItem); }
}

// ── Tray icon ─────────────────────────────────────────────────────────────────

unsafe fn tray_add(hwnd: HWND) {
    let mut d: NOTIFYICONDATAW = zeroed();
    d.cbSize = mem::size_of::<NOTIFYICONDATAW>() as u32;
    d.hWnd   = hwnd;
    d.uID    = TRAY_UID;
    d.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
    d.uCallbackMessage = WM_TRAYICON;
    d.hIcon  = APP_ICON.get().map(|i| i.0).unwrap_or(LoadIconW(ptr::null_mut(), IDI_APPLICATION));
    let tip  = wstr("OpenVyapar ERP");
    d.szTip[..tip.len().min(128)].copy_from_slice(&tip[..tip.len().min(128)]);
    Shell_NotifyIconW(NIM_ADD, &mut d);
}

unsafe fn tray_remove(hwnd: HWND) {
    let mut d: NOTIFYICONDATAW = zeroed();
    d.cbSize = mem::size_of::<NOTIFYICONDATAW>() as u32;
    d.hWnd = hwnd; d.uID = TRAY_UID;
    Shell_NotifyIconW(NIM_DELETE, &mut d);
}

unsafe fn tray_menu(hwnd: HWND) {
    let running = is_running();
    let menu    = CreatePopupMenu();
    AppendMenuW(menu, MF_STRING, 1, wstr("Open in Browser").as_ptr());
    AppendMenuW(menu, MF_SEPARATOR, 0, ptr::null());
    if running {
        AppendMenuW(menu, MF_STRING, 2, wstr("Stop Servers").as_ptr());
        AppendMenuW(menu, MF_STRING, 3, wstr("Restart Servers").as_ptr());
    } else {
        AppendMenuW(menu, MF_STRING, 4, wstr("Start Servers").as_ptr());
    }
    AppendMenuW(menu, MF_STRING,    5, wstr("Backup Now").as_ptr());
    AppendMenuW(menu, MF_SEPARATOR, 0, ptr::null());
    AppendMenuW(menu, MF_STRING,    6, wstr("Show Window").as_ptr());
    AppendMenuW(menu, MF_STRING,    7, wstr("Exit (Stop & Quit)").as_ptr());

    let mut pt: POINT = zeroed();
    GetCursorPos(&mut pt);
    SetForegroundWindow(hwnd);
    let cmd = TrackPopupMenu(menu, TPM_RETURNCMD | TPM_NONOTIFY | TPM_RIGHTBUTTON, pt.x, pt.y, 0, hwnd, ptr::null());
    DestroyMenu(menu);

    match cmd as u32 {
        1 => { let _ = open::that(format!("http://localhost:{}", PORT_UI)); }
        2 => do_stop(hwnd),
        3 => do_restart(hwnd),
        4 => do_start(hwnd),
        5 => do_backup(hwnd),
        6 => { ShowWindow(hwnd, SW_SHOW); SetForegroundWindow(hwnd); }
        7 => { tray_remove(hwnd); do_stop(hwnd); PostQuitMessage(0); }
        _ => {}
    }
}

// ── Window procedure ──────────────────────────────────────────────────────────

unsafe extern "system" fn wnd_proc(hwnd: HWND, msg: UINT, wp: WPARAM, lp: LPARAM) -> LRESULT {
    match msg {

        WM_CREATE => {
            let hi = HINST.get().unwrap().0;

            // ── Header bar ──────────────────────────────────────────────────
            let hdr = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  OpenVyapar ERP  \u{2014}  Server Launcher").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                0, 0, 480, 46, hwnd, hm(ID_HEADER), hi, ptr::null_mut());
            SendMessageW(hdr, WM_SETFONT,
                make_font(17, font_bold(), "Segoe UI") as WPARAM, 1);

            // ── Status label ────────────────────────────────────────────────
            let lbl = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  \u{25CF}  Stopped").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                14, 52, 300, 22, hwnd, hm(ID_LBL_STATUS), hi, ptr::null_mut());
            SendMessageW(lbl, WM_SETFONT,
                make_font(15, font_semibold(), "Segoe UI") as WPARAM, 1);

            // ── Row 1: Start / Stop / Restart ───────────────────────────────
            let (y1, bh) = (82i32, 44i32);
            for (id, x, w) in [(ID_BTN_START, 14i32, 148i32), (ID_BTN_STOP, 168, 148), (ID_BTN_RESTART, 322, 144)] {
                CreateWindowExW(0, wstr("BUTTON").as_ptr(), wstr("").as_ptr(),
                    WS_CHILD | WS_VISIBLE | BS_OWNERDRAW,
                    x, y1, w, bh, hwnd, hm(id), hi, ptr::null_mut());
            }

            // ── Row 2: Browser / Backup / Tray ──────────────────────────────
            let y2 = y1 + bh + 8;
            for (id, x, w) in [(ID_BTN_BROWSER, 14i32, 148i32), (ID_BTN_BACKUP, 168, 148), (ID_BTN_TRAY, 322, 144)] {
                CreateWindowExW(0, wstr("BUTTON").as_ptr(), wstr("").as_ptr(),
                    WS_CHILD | WS_VISIBLE | BS_OWNERDRAW,
                    x, y2, w, bh, hwnd, hm(id), hi, ptr::null_mut());
            }

            // ── Log label ───────────────────────────────────────────────────
            let ly = y2 + bh + 10;
            let sm = make_font(12, font_normal(), "Segoe UI") as WPARAM;
            let div = CreateWindowExW(0, wstr("STATIC").as_ptr(), wstr("  Activity Log").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                14, ly, 200, 14, hwnd, ptr::null_mut(), hi, ptr::null_mut());
            SendMessageW(div, WM_SETFONT, sm, 1);

            // ── Log list-box (Consolas mono) ────────────────────────────────
            let lb_y = ly + 16;
            let lb = CreateWindowExW(WS_EX_CLIENTEDGE, wstr("LISTBOX").as_ptr(), ptr::null(),
                WS_CHILD | WS_VISIBLE | WS_VSCROLL | LBS_NOINTEGRALHEIGHT | LBS_NOTIFY,
                14, lb_y, 452, 155, hwnd, hm(ID_LISTBOX), hi, ptr::null_mut());
            SendMessageW(lb, WM_SETFONT,
                make_font(11, font_normal(), "Consolas") as WPARAM, 1);

            // ── Hint ────────────────────────────────────────────────────────
            let hint = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  Closing this window hides it to the system tray. Right-click tray icon to exit.").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                14, lb_y + 155 + 4, 452, 14, hwnd, ptr::null_mut(), hi, ptr::null_mut());
            SendMessageW(hint, WM_SETFONT, sm, 1);

            // ── Header brush (navy) ─────────────────────────────────────────
            HDR_BRUSH.set(SHBrush(CreateSolidBrush(RGB(26, 35, 126)))).ok();

            tray_add(hwnd);
            PostMessageW(hwnd, WM_COMMAND, ID_BTN_START as WPARAM, 0);
            0
        }

        // Owner-draw buttons
        WM_DRAWITEM => {
            let dis = &*(lp as *const DRAWITEMSTRUCT);
            if dis.CtlType == ODT_BUTTON { draw_button(dis); return TRUE as LRESULT; }
            DefWindowProcW(hwnd, msg, wp, lp)
        }

        WM_COMMAND => {
            match loword(wp) {
                x if x == ID_BTN_START   => do_start(hwnd),
                x if x == ID_BTN_STOP    => do_stop(hwnd),
                x if x == ID_BTN_RESTART => do_restart(hwnd),
                x if x == ID_BTN_BROWSER => { let _ = open::that(format!("http://localhost:{}", PORT_UI)); }
                x if x == ID_BTN_BACKUP  => do_backup(hwnd),
                x if x == ID_BTN_TRAY    => { ShowWindow(hwnd, SW_HIDE); }
                _ => {}
            }
            0
        }

        WM_TIMER => {
            if wp == TIMER_BROWSER {
                KillTimer(hwnd, TIMER_BROWSER);
                let _ = open::that(format!("http://localhost:{}", PORT_UI));
                log_msg(hwnd, "Browser opened.");
            }
            0
        }

        // Colour the header + status label
        WM_CTLCOLORSTATIC => {
            let ctrl = lp as HWND;
            let id   = GetDlgCtrlID(ctrl);
            let hdc  = wp as HDC;

            if id == ID_HEADER as i32 {
                SetTextColor(hdc, RGB(255, 255, 255));
                SetBkColor(hdc,   RGB(26, 35, 126));
                if let Some(b) = HDR_BRUSH.get() { return b.0 as LRESULT; }
            }

            if id == ID_LBL_STATUS as i32 {
                let col = if is_running() { RGB(46, 125, 50) } else { RGB(198, 40, 40) };
                SetTextColor(hdc, col);
                SetBkMode(hdc, TRANSPARENT as i32);
                return GetStockObject(NULL_BRUSH as i32) as LRESULT;
            }

            DefWindowProcW(hwnd, msg, wp, lp)
        }

        WM_TRAYICON => {
            match lp as UINT {
                WM_RBUTTONUP | WM_CONTEXTMENU => tray_menu(hwnd),
                WM_LBUTTONDBLCLK => { ShowWindow(hwnd, SW_SHOW); SetForegroundWindow(hwnd); }
                _ => {}
            }
            0
        }

        WM_CLOSE   => { ShowWindow(hwnd, SW_HIDE); 0 }
        WM_DESTROY => { tray_remove(hwnd); do_stop(hwnd); PostQuitMessage(0); 0 }
        _          => DefWindowProcW(hwnd, msg, wp, lp),
    }
}

// ── Entry point ───────────────────────────────────────────────────────────────

fn main() {
    unsafe {
        let hi = GetModuleHandleW(ptr::null());
        HINST.set(SHInstance(hi)).ok();
        STATE.set(Mutex::new(AppState { api_proc: None, ui_proc: None, running: false })).ok();

        let icon = create_icon();
        APP_ICON.set(SHIcon(icon)).ok();

        let icc = INITCOMMONCONTROLSEX {
            dwSize: mem::size_of::<INITCOMMONCONTROLSEX>() as u32,
            dwICC: ICC_WIN95_CLASSES | ICC_STANDARD_CLASSES,
        };
        InitCommonControlsEx(&icc);

        let cls = wstr("OvLauncher3");
        let wc  = WNDCLASSEXW {
            cbSize: mem::size_of::<WNDCLASSEXW>() as u32,
            style: CS_HREDRAW | CS_VREDRAW,
            lpfnWndProc: Some(wnd_proc),
            cbClsExtra: 0, cbWndExtra: 0,
            hInstance: hi,
            hIcon: icon,
            hIconSm: icon,
            hCursor: LoadCursorW(ptr::null_mut(), IDC_ARROW),
            hbrBackground: (COLOR_WINDOW + 1) as HBRUSH,
            lpszMenuName: ptr::null(),
            lpszClassName: cls.as_ptr(),
        };
        if RegisterClassExW(&wc) == 0 { return; }

        // Window: fixed size, no maximize/resize
        let hwnd = CreateWindowExW(
            WS_EX_APPWINDOW,
            cls.as_ptr(),
            wstr("OpenVyapar ERP").as_ptr(),
            WS_OVERLAPPEDWINDOW & !(WS_MAXIMIZEBOX | WS_THICKFRAME),
            CW_USEDEFAULT, CW_USEDEFAULT, 480, 420,
            ptr::null_mut(), ptr::null_mut(), hi, ptr::null_mut(),
        );
        if hwnd.is_null() { return; }

        // Centre on screen
        let sw = GetSystemMetrics(SM_CXSCREEN);
        let sh = GetSystemMetrics(SM_CYSCREEN);
        let mut r: RECT = zeroed();
        GetWindowRect(hwnd, &mut r);
        SetWindowPos(hwnd, ptr::null_mut(),
            (sw - (r.right - r.left)) / 2,
            (sh - (r.bottom - r.top)) / 2,
            0, 0, SWP_NOSIZE | SWP_NOZORDER);

        ShowWindow(hwnd, SW_SHOW);
        UpdateWindow(hwnd);

        let mut msg: MSG = zeroed();
        while GetMessageW(&mut msg, ptr::null_mut(), 0, 0) > 0 {
            TranslateMessage(&msg);
            DispatchMessageW(&msg);
        }
    }
}
