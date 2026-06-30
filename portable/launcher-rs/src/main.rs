#![windows_subsystem = "windows"]

use std::mem::{self, zeroed};
use std::ffi::OsStr;
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
use winapi::um::winuser::*;
use winapi::um::wingdi::*;

// ── Constants ────────────────────────────────────────────────────────────────

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

// ── Global state ─────────────────────────────────────────────────────────────

struct AppState {
    api_proc: Option<Child>,
    ui_proc:  Option<Child>,
    running:  bool,
}

static STATE: OnceLock<Mutex<AppState>> = OnceLock::new();
struct SendableHInstance(HINSTANCE);
unsafe impl Send for SendableHInstance {}
unsafe impl Sync for SendableHInstance {}

static HINST: OnceLock<SendableHInstance> = OnceLock::new();

// ── Helpers ──────────────────────────────────────────────────────────────────

fn wstr(s: &str) -> Vec<u16> {
    OsStr::new(s).encode_wide().chain(std::iter::once(0)).collect()
}

fn loword(x: WPARAM) -> u16 { (x & 0xFFFF) as u16 }

fn hm(id: u16) -> HMENU { id as usize as HMENU }

fn root_dir() -> PathBuf {
    std::env::current_exe().unwrap().parent().unwrap().to_path_buf()
}

fn ts() -> String {
    let s = SystemTime::now().duration_since(UNIX_EPOCH).unwrap_or_default().as_secs();
    format!("{:02}:{:02}:{:02}", (s / 3600) % 24, (s / 60) % 60, s % 60)
}

fn is_running() -> bool {
    STATE.get().map(|m| m.lock().unwrap().running).unwrap_or(false)
}

fn make_font(size: i32, bold: bool) -> HFONT {
    unsafe {
        CreateFontW(
            size, 0, 0, 0,
            if bold { FW_BOLD as i32 } else { FW_NORMAL as i32 },
            0, 0, 0, DEFAULT_CHARSET as u32,
            OUT_DEFAULT_PRECIS as u32, CLIP_DEFAULT_PRECIS as u32,
            CLEARTYPE_QUALITY as u32, DEFAULT_PITCH as u32,
            wstr("Segoe UI").as_ptr(),
        )
    }
}

// ── Log ──────────────────────────────────────────────────────────────────────

fn log_msg(hwnd: HWND, msg: &str) {
    unsafe {
        let lb   = GetDlgItem(hwnd, ID_LISTBOX as i32);
        let line = wstr(&format!("[{}]  {}", ts(), msg));
        let idx  = SendMessageW(lb, LB_ADDSTRING, 0, line.as_ptr() as LPARAM);
        let cnt  = SendMessageW(lb, LB_GETCOUNT, 0, 0);
        if cnt > 200 { SendMessageW(lb, LB_DELETESTRING, 0, 0); }
        SendMessageW(lb, LB_SETCURSEL, idx as WPARAM, 0);
    }
}

// ── Server control ───────────────────────────────────────────────────────────

fn do_start(hwnd: HWND) {
    let root = root_dir();
    let php  = root.join("php").join("php.exe");
    let app  = root.join("app");
    let www  = root.join("www");

    if !php.exists() {
        log_msg(hwnd, "ERROR: php\\php.exe not found in portable package.");
        return;
    }

    log_msg(hwnd, &format!("Starting API server  ->  http://localhost:{}", PORT_API));
    let api = Command::new(&php)
        .args(["-S", &format!("localhost:{}", PORT_API), "-t", "public", "public\\index.php"])
        .current_dir(&app)
        .stdin(std::process::Stdio::null())
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null())
        .creation_flags(CREATE_NO_WINDOW)
        .spawn();

    log_msg(hwnd, &format!("Starting UI  server  ->  http://localhost:{}", PORT_UI));
    let ui = Command::new(&php)
        .args(["-S", &format!("localhost:{}", PORT_UI), "-t", www.to_str().unwrap_or("www")])
        .current_dir(&www)
        .stdin(std::process::Stdio::null())
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null())
        .creation_flags(CREATE_NO_WINDOW)
        .spawn();

    let ok_api = api.is_ok();
    let ok_ui  = ui.is_ok();

    if let Some(m) = STATE.get() {
        let mut s  = m.lock().unwrap();
        s.api_proc = api.ok();
        s.ui_proc  = ui.ok();
        s.running  = ok_api && ok_ui;
    }

    if ok_api && ok_ui {
        log_msg(hwnd, "Servers started. Opening browser in 3 seconds...");
        unsafe { SetTimer(hwnd, TIMER_BROWSER, 3000, None); }
    } else {
        if !ok_api { log_msg(hwnd, "ERROR: Failed to start API server."); }
        if !ok_ui  { log_msg(hwnd, "ERROR: Failed to start UI  server."); }
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
    log_msg(hwnd, "Restarting servers...");
    do_start(hwnd);
}

fn do_backup(hwnd: HWND) {
    let root = root_dir();
    let src  = root.join("app").join("database").join("openvyapar.sqlite");
    if !src.exists() {
        log_msg(hwnd, "Backup FAILED: database not found.");
        return;
    }
    let dir = root.join("backups");
    let _   = std::fs::create_dir_all(&dir);
    let ts_val = SystemTime::now().duration_since(UNIX_EPOCH).unwrap_or_default().as_secs();
    let name   = format!("backup_{}.sqlite", ts_val);
    match std::fs::copy(&src, dir.join(&name)) {
        Ok(_)  => log_msg(hwnd, &format!("Backup saved  ->  backups\\{}", name)),
        Err(e) => log_msg(hwnd, &format!("Backup FAILED: {}", e)),
    }
}

// ── UI update ────────────────────────────────────────────────────────────────

fn update_ui(hwnd: HWND) {
    unsafe {
        let running = is_running();
        let label   = if running { "  Running" } else { "  Stopped" };
        SetWindowTextW(GetDlgItem(hwnd, ID_LBL_STATUS as i32), wstr(label).as_ptr());
        InvalidateRect(GetDlgItem(hwnd, ID_LBL_STATUS as i32), ptr::null(), TRUE);

        EnableWindow(GetDlgItem(hwnd, ID_BTN_START   as i32), if running { FALSE } else { TRUE });
        EnableWindow(GetDlgItem(hwnd, ID_BTN_STOP    as i32), if running { TRUE  } else { FALSE });
        EnableWindow(GetDlgItem(hwnd, ID_BTN_RESTART as i32), if running { TRUE  } else { FALSE });
        EnableWindow(GetDlgItem(hwnd, ID_BTN_BROWSER as i32), if running { TRUE  } else { FALSE });
    }
}

// ── Tray ─────────────────────────────────────────────────────────────────────

unsafe fn tray_add(hwnd: HWND) {
    let mut d: NOTIFYICONDATAW = zeroed();
    d.cbSize = mem::size_of::<NOTIFYICONDATAW>() as u32;
    d.hWnd   = hwnd;
    d.uID    = TRAY_UID;
    d.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
    d.uCallbackMessage = WM_TRAYICON;
    d.hIcon  = LoadIconW(ptr::null_mut(), IDI_APPLICATION);
    let tip  = wstr("OpenVyapar ERP");
    let len  = tip.len().min(128);
    d.szTip[..len].copy_from_slice(&tip[..len]);
    Shell_NotifyIconW(NIM_ADD, &mut d);
}

unsafe fn tray_remove(hwnd: HWND) {
    let mut d: NOTIFYICONDATAW = zeroed();
    d.cbSize = mem::size_of::<NOTIFYICONDATAW>() as u32;
    d.hWnd   = hwnd;
    d.uID    = TRAY_UID;
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
    AppendMenuW(menu, MF_STRING, 5, wstr("Backup Now").as_ptr());
    AppendMenuW(menu, MF_SEPARATOR, 0, ptr::null());
    AppendMenuW(menu, MF_STRING, 6, wstr("Show Window").as_ptr());
    AppendMenuW(menu, MF_STRING, 7, wstr("Exit (Stop & Quit)").as_ptr());

    let mut pt: POINT = zeroed();
    GetCursorPos(&mut pt);
    SetForegroundWindow(hwnd);
    let cmd = TrackPopupMenu(menu, TPM_RETURNCMD | TPM_NONOTIFY | TPM_RIGHTBUTTON,
        pt.x, pt.y, 0, hwnd, ptr::null());
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

// ── Window procedure ─────────────────────────────────────────────────────────

unsafe extern "system" fn wnd_proc(hwnd: HWND, msg: UINT, wp: WPARAM, lp: LPARAM) -> LRESULT {
    match msg {

        WM_CREATE => {
            let hi    = HINST.get().unwrap().0;
            let fnorm = make_font(15, false);
            let fbold = make_font(17, true);
            let fsmall= make_font(13, false);

            // Header bar (static text acts as heading)
            let hdr = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  OpenVyapar ERP  -  Server Launcher").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                0, 0, 480, 38, hwnd, hm(0), hi, ptr::null_mut());
            SendMessageW(hdr, WM_SETFONT, fbold as WPARAM, 1);

            // Status row
            let lbl = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  Stopped").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                12, 46, 300, 22, hwnd, hm(ID_LBL_STATUS), hi, ptr::null_mut());
            SendMessageW(lbl, WM_SETFONT, make_font(15, true) as WPARAM, 1);

            // Row 1: Start / Stop / Restart
            for (id, label, x, w) in [
                (ID_BTN_START,   "Start Servers", 12i32,  148i32),
                (ID_BTN_STOP,    "Stop Servers",  168,    148),
                (ID_BTN_RESTART, "Restart",       324,    140),
            ] {
                let b = CreateWindowExW(0, wstr("BUTTON").as_ptr(), wstr(label).as_ptr(),
                    WS_CHILD | WS_VISIBLE | BS_PUSHBUTTON,
                    x, 76, w, 34, hwnd, hm(id), hi, ptr::null_mut());
                SendMessageW(b, WM_SETFONT, fnorm as WPARAM, 1);
            }

            // Row 2: Browser / Backup / Minimize to Tray
            for (id, label, x, w) in [
                (ID_BTN_BROWSER, "Open Browser",     12i32,  148i32),
                (ID_BTN_BACKUP,  "Backup Now",       168,    148),
                (ID_BTN_TRAY,    "Minimize to Tray", 324,    140),
            ] {
                let b = CreateWindowExW(0, wstr("BUTTON").as_ptr(), wstr(label).as_ptr(),
                    WS_CHILD | WS_VISIBLE | BS_PUSHBUTTON,
                    x, 118, w, 34, hwnd, hm(id), hi, ptr::null_mut());
                SendMessageW(b, WM_SETFONT, fnorm as WPARAM, 1);
            }

            // Divider label
            let div = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  Activity Log").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                12, 162, 200, 16, hwnd, hm(0), hi, ptr::null_mut());
            SendMessageW(div, WM_SETFONT, fsmall as WPARAM, 1);

            // Log listbox
            let lb = CreateWindowExW(WS_EX_CLIENTEDGE, wstr("LISTBOX").as_ptr(), ptr::null(),
                WS_CHILD | WS_VISIBLE | WS_VSCROLL | LBS_NOINTEGRALHEIGHT | LBS_NOTIFY,
                12, 180, 452, 172, hwnd, hm(ID_LISTBOX), hi, ptr::null_mut());
            SendMessageW(lb, WM_SETFONT, fsmall as WPARAM, 1);

            // Bottom hint
            let hint = CreateWindowExW(0, wstr("STATIC").as_ptr(),
                wstr("  Closing hides to tray. Right-click tray icon to exit.").as_ptr(),
                WS_CHILD | WS_VISIBLE | SS_LEFT,
                12, 358, 452, 16, hwnd, hm(0), hi, ptr::null_mut());
            SendMessageW(hint, WM_SETFONT, fsmall as WPARAM, 1);

            tray_add(hwnd);

            // Auto-start on launch
            PostMessageW(hwnd, WM_COMMAND, ID_BTN_START as WPARAM, 0);
            0
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

        // Colour status label green/red
        WM_CTLCOLORSTATIC => {
            if GetDlgCtrlID(lp as HWND) == ID_LBL_STATUS as i32 {
                let hdc = wp as HDC;
                let col = if is_running() { RGB(0, 150, 50) } else { RGB(180, 30, 30) };
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

        WM_CLOSE => { ShowWindow(hwnd, SW_HIDE); 0 }

        WM_DESTROY => {
            tray_remove(hwnd);
            do_stop(hwnd);
            PostQuitMessage(0);
            0
        }

        _ => DefWindowProcW(hwnd, msg, wp, lp),
    }
}

// ── main ─────────────────────────────────────────────────────────────────────

fn main() {
    unsafe {
        let hi = GetModuleHandleW(ptr::null());
        HINST.set(SendableHInstance(hi)).ok();
        STATE.set(Mutex::new(AppState { api_proc: None, ui_proc: None, running: false })).ok();

        let icc = INITCOMMONCONTROLSEX {
            dwSize: mem::size_of::<INITCOMMONCONTROLSEX>() as u32,
            dwICC: ICC_WIN95_CLASSES | ICC_STANDARD_CLASSES,
        };
        InitCommonControlsEx(&icc);

        let cls = wstr("OvLauncher");
        let wc  = WNDCLASSEXW {
            cbSize:        mem::size_of::<WNDCLASSEXW>() as u32,
            style:         CS_HREDRAW | CS_VREDRAW,
            lpfnWndProc:   Some(wnd_proc),
            cbClsExtra:    0,
            cbWndExtra:    0,
            hInstance:     hi,
            hIcon:         LoadIconW(ptr::null_mut(), IDI_APPLICATION),
            hCursor:       LoadCursorW(ptr::null_mut(), IDC_ARROW),
            hbrBackground: (COLOR_BTNFACE + 1) as HBRUSH,
            lpszMenuName:  ptr::null(),
            lpszClassName: cls.as_ptr(),
            hIconSm:       LoadIconW(ptr::null_mut(), IDI_APPLICATION),
        };
        if RegisterClassExW(&wc) == 0 { return; }

        let hwnd = CreateWindowExW(
            WS_EX_APPWINDOW,
            cls.as_ptr(),
            wstr("OpenVyapar ERP").as_ptr(),
            WS_OVERLAPPEDWINDOW & !(WS_MAXIMIZEBOX | WS_THICKFRAME),
            CW_USEDEFAULT, CW_USEDEFAULT, 480, 400,
            ptr::null_mut(), ptr::null_mut(), hi, ptr::null_mut(),
        );
        if hwnd.is_null() { return; }

        // Centre on screen
        let sw = GetSystemMetrics(SM_CXSCREEN);
        let sh = GetSystemMetrics(SM_CYSCREEN);
        let mut r: RECT = zeroed();
        GetWindowRect(hwnd, &mut r);
        SetWindowPos(hwnd, ptr::null_mut(),
            (sw - (r.right  - r.left)) / 2,
            (sh - (r.bottom - r.top))  / 2,
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
