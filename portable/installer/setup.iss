; ─────────────────────────────────────────────────────────────────
;  OpenVyapar ERP - Inno Setup Installer Script
;  Build with: Inno Setup 6+ (https://jrsoftware.org/isinfo.php)
;
;  Before building:
;    1. Run build\build-portable.bat to create dist\OpenVyapar-ERP-Portable\
;    2. Open this file in Inno Setup Compiler
;    3. Press Ctrl+F9 to compile
; ─────────────────────────────────────────────────────────────────

#define MyAppName      "OpenVyapar ERP"
#define MyAppVersion   "1.0.0"
#define MyAppPublisher "MANORANJAN"
#define MyAppURL       "https://manoranjan.dev"
#define MyAppExeName   "start.bat"
#define MySourceDir    "..\dist\OpenVyapar-ERP-Portable"

[Setup]
AppId={{A4B7C2D1-E3F4-5678-9ABC-DEF012345678}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} v{#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL=https://github.com/manoranjan2050/OpenVyapar-ERP/issues
AppUpdatesURL=https://github.com/manoranjan2050/OpenVyapar-ERP/releases
DefaultDirName=C:\OpenVyapar
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
LicenseFile=..\..\LICENSE
OutputDir=..\dist
OutputBaseFilename=OpenVyapar-ERP-Setup-v{#MyAppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
SetupIconFile=assets\icon.ico
UninstallDisplayIcon={app}\start.bat
PrivilegesRequired=lowest
PrivilegesRequiredOverridesAllowed=dialog
ArchitecturesInstallIn64BitMode=x64
MinVersion=10.0.17763
WizardResizable=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked
Name: "startupicon"; Description: "Start OpenVyapar ERP when Windows starts"; GroupDescription: "Startup:"; Flags: unchecked

[Files]
; Copy the entire portable build
Source: "{#MySourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
; Start menu
Name: "{group}\{#MyAppName}";        Filename: "{app}\start.bat";   IconFilename: "{app}\assets\icon.ico"; WorkingDir: "{app}"
Name: "{group}\Stop Server";          Filename: "{app}\stop.bat";    IconFilename: "{app}\assets\icon.ico"; WorkingDir: "{app}"
Name: "{group}\Reset to Factory";     Filename: "{app}\launcher\reset.bat"; WorkingDir: "{app}"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"

; Desktop
Name: "{autodesktop}\{#MyAppName}";  Filename: "{app}\start.bat";   IconFilename: "{app}\assets\icon.ico"; WorkingDir: "{app}"; Tasks: desktopicon

; Startup
Name: "{userstartup}\{#MyAppName}";  Filename: "{app}\start.bat";   WorkingDir: "{app}"; Tasks: startupicon

[Run]
Filename: "{app}\start.bat"; Description: "Launch OpenVyapar ERP now"; Flags: postinstall nowait shellexec skipifsilent

[UninstallRun]
Filename: "{app}\stop.bat"; Flags: shellexec; RunOnceId: "StopServer"

[Code]
procedure InitializeWizard;
begin
  WizardForm.WelcomeLabel2.Caption :=
    'This will install OpenVyapar ERP v{#MyAppVersion} on your computer.' + #13#10 + #13#10 +
    'OpenVyapar ERP is a free, open-source GST-ready ERP for Indian small businesses.' + #13#10 + #13#10 +
    'Includes: PHP 8.2 (portable) + SQLite database + Vue 3 frontend' + #13#10 +
    'No internet connection required after installation.' + #13#10 + #13#10 +
    'Default login after setup:  admin@demo.com  /  password';
end;
