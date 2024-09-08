#define MyAppName "GOG Downloader"
#define MyAppVersion "1.6.1"
#define MyAppPublisher "RikudouSage"
#define MyAppURL "https://github.com/RikudouSage/GogDownloader"
#define MyAppExeName "GogDownloader.exe"
#define PhpLink "https://windows.php.net/downloads/releases/php-8.3.11-nts-Win32-vs16-x64.zip"
#define PhpSha "2e302fd376a67cfb43dfd6c8fec1ccd503604cee2717fddc4098fedfb778ddb9"
#define VcRedistLink "https://aka.ms/vs/17/release/vc_redist.x64.exe"

[Setup]
AppId={{F364EE12-B389-4F21-9813-832F24B4BFBF}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
;AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
; Uncomment the following line to run in non administrative install mode (install for current user only.)
PrivilegesRequired=lowest
PrivilegesRequiredOverridesAllowed=dialog
OutputDir=.
OutputBaseFilename=GogDownloaderSetup
Compression=lzma
SolidCompression=yes
WizardStyle=modern

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"
Name: "czech"; MessagesFile: "compiler:Languages\Czech.isl"

[Files]
Source: ".\bin\*"; DestDir: "{app}\php-bin"; Flags: ignoreversion recursesubdirs
Source: ".\config\*"; DestDir: "{app}\config"; Flags: ignoreversion recursesubdirs
Source: ".\src\*"; DestDir: "{app}\src"; Flags: ignoreversion recursesubdirs
Source: ".\vendor\*"; DestDir: "{app}\vendor"; Flags: ignoreversion recursesubdirs
Source: ".\composer.json"; DestDir: "{app}"; Flags: ignoreversion
Source: ".\composer.lock"; DestDir: "{app}"; Flags: ignoreversion
Source: ".\windows\unzip.exe"; DestDir: "{tmp}"; Flags: ignoreversion
Source: ".\windows\gog-downloader.bat"; DestDir: "{app}\bin"; Flags: ignoreversion

[Icons]
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"

[Run]
Filename: "{tmp}\VC_redist.x64.exe"; StatusMsg: "{cm:InstallingVC2017redist}"; Parameters: "/quiet"; Check: VS17RedistNeedsInstall; Flags: waituntilterminated
Filename: "cmd.exe"; Description: "{cm:OpenCmd}"; Parameters: "/K ""cd %USERPROFILE% && echo Run the gog-downloader command to view usage instructions or to start using it. Note that if you get an error about the command not being recognized, you might need to reboot your PC."""; Flags: nowait postinstall skipifsilent

[CustomMessages]
english.DownloadPhpPageTitle=Downloading runtime
english.DownloadPhpPageDescription=The setup is now downloading the latest supported php runtime, please wait.
english.DownloadPhpFailed=Downloading the php runtime failed. Please try again later or check whether there's an update version of this app.
english.DownloadPhpExtracting=The setup is now extracting the php runtime, please wait.
english.ExtractPhpFailed=Extracting the php runtime failed.
english.CopyingPhp=The setup is now copying the extracted php runtime to the target directory.
english.DownloadFinished=The php runtime has been successfully created.
english.CopyFileFailed=Copying one of the required files failed. Please try again later.
english.InstallingVC2017redist=Installing VC Redist...
english.OpenCmd=Open a new command line window
english.FinishText=GOG Downloader has been installed and can now be used from any command line or PowerShell window, by invoking the gog-downloader command.

czech.DownloadPhpPageTitle=Stahování rozhraní
czech.DownloadPhpPageDescription=Instalátor nyní stahuje nejnovější podporované php rozhraní, prosím čekejte.
czech.DownloadPhpFailed=Stahování php rozhraní selhalo. Zkuste to znovu později, nebo zkontrolujte, zda neexistuje novější verze této aplikace.
czech.DownloadPhpExtracting=Instalátor nyní extrahuje php rozhraní, prosím čekejte.
czech.ExtractPhpFailed=Extrahování php rozhraní selhalo.
czech.CopyingPhp=Instalátor nyní kopíruje extrahované php rozhraní do cílové složky.
czech.DownloadFinished=Php rozhraní bylo úspěšně vytvořeno.
czech.CopyFileFailed=Kopírování jednoho z nezbytných souborů selhalo. Zkuste to prosím znovu.
czech.InstallingVC2017redist=Instalace VC Redist...
czech.OpenCmd=Otevřít nové okno příkazové řádky
czech.FinishText=GOG Downloader byl nainstalován a můžete jej nyní použít z kteréhokoli okna příkazové řádky nebo PowerShellu pomocí příkazu gog-downloader.

[UninstallDelete]
Type: filesandordirs; Name: "{app}\php\*"
Type: dirifempty; Name: "{app}\php"

[Code]
var DownloadPhpPage: TWizardPage;
var TmpDir: String;

procedure ExitProcess(uExitCode: Integer);
  external 'ExitProcess@kernel32.dll stdcall';
  
function VS17RedistNeedsInstall: Boolean;
var 
  Version: String;
begin
  if RegQueryStringValue(HKEY_LOCAL_MACHINE,
       'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64', 'Version',
       Version) then
  begin
    Log('VC Redist Version check : found ' + Version);
    Result := (CompareStr(Version, 'v14.14.26429.03')<0);
  end
  else 
  begin
    Result := True;
  end;
  
  if (Result) then
  begin
    DownloadTemporaryFile('{#VcRedistLink}', 'VC_redist.x64.exe', '', nil);
  end;
end;

procedure DirectoryCopy(SourcePath, DestPath: string);
var
  FindRec: TFindRec;
  SourceFilePath: string;
  DestFilePath: string;
begin
  if not DirExists(DestPath) and not CreateDir(DestPath) then
  begin
    RaiseException(Format('Could not create directory %s', [DestFilePath]));
  end;
  
  if FindFirst(SourcePath + '\*', FindRec) then
  begin
    try
      repeat
        if (FindRec.Name <> '.') and (FindRec.Name <> '..') then
        begin
          SourceFilePath := SourcePath + '\' + FindRec.Name;
          DestFilePath := DestPath + '\' + FindRec.Name;
          if FindRec.Attributes and FILE_ATTRIBUTE_DIRECTORY = 0 then
          begin
            if FileCopy(SourceFilePath, DestFilePath, False) then
            begin
              Log(Format('Copied %s to %s', [SourceFilePath, DestFilePath]));
            end
            else
            begin
              RaiseException(Format('Failed to copy %s to %s', [SourceFilePath, DestFilePath]));
            end;
          end
          else
          begin
            if DirExists(DestFilePath) or CreateDir(DestFilePath) then
            begin
              Log(Format('Created %s', [DestFilePath]));
              DirectoryCopy(SourceFilePath, DestFilePath);
            end
            else
            begin
              RaiseException(Format('Failed to create %s', [DestFilePath]));
            end;
          end;
        end;
      until not FindNext(FindRec);
    finally
      FindClose(FindRec);
    end;
  end
  else
  begin
    RaiseException(Format('Failed to list %s', [SourcePath]));
  end;
end;
  
procedure InitializeWizard;
begin
  TmpDir := ExpandConstant('{tmp}');
  DownloadPhpPage := CreateCustomPage(wpInstalling, ExpandConstant('{cm:DownloadPhpPageTitle}'), ExpandConstant('{cm:DownloadPhpPageDescription}'));
end;

function GetTargetPathSection: Integer;
begin
  if IsAdminInstallMode() then
  begin
    Result := HKEY_LOCAL_MACHINE;
  end
  else 
    Result := HKEY_CURRENT_USER;
end;

function GetTargetPathKey: String;
begin
  if IsAdminInstallMode() then
  begin
    Result := 'SYSTEM\CurrentControlSet\Control\Session Manager\Environment';
  end
  else
  begin 
    Result := 'Environment';
  end;
end;

function GetPathValue: String;
  var OrigPath: String;
begin
  if not RegQueryStringValue(GetTargetPathSection(), GetTargetPathKey(), 'Path', OrigPath) then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  Result := OrigPath;
end;

function PathExistsInPath(Path: String): Boolean;
  var OrigPath: String;
begin  
  OrigPath := GetPathValue();
  Result := Pos(';' + Path + ';', ';' + OrigPath + ';') <> 0;
end;

procedure FinishedPageHandler;
begin
  WizardForm.FinishedLabel.Caption := ExpandConstant('{cm:FinishText}');
end;

procedure DownloadPhpPageHandler;
  var AppDir: String;
  var DownloadSuccess: Boolean;
  var ResultCode: Integer;
  var PhpIniFile: String;
  var PhpIniContentAnsi: AnsiString;
  var PhpIniContent: String;
  var BinPath: String;
begin 
  AppDir := ExpandConstant('{app}');
  BinPath := AppDir + '\bin';
  WizardForm.NextButton.Enabled := False;
  WizardForm.Update;
  
  try
    DownloadTemporaryFile('{#PhpLink}', 'php.zip', '{#PhpSha}', nil);
    DownloadSuccess := True;
  except
    DownloadSuccess := False;
  end;
  
  if not DownloadSuccess then
  begin
    MsgBox(ExpandConstant('{cm:DownloadPhpFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  DownloadPhpPage.Description := ExpandConstant('{cm:DownloadPhpExtracting}');
  
  if not Exec(TmpDir + '\unzip.exe', TmpDir + '\php.zip -d php', TmpDir, SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    MsgBox(ExpandConstant('{cm:ExtractPhpFailed'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  DownloadPhpPage.Description := ExpandConstant('{cm:CopyingPhp}');
  
  try
    DirectoryCopy(TmpDir + '\php', AppDir + '\php');
  except
    MsgBox(GetExceptionMessage(), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  if not FileCopy(AppDir + '\php\php.ini-production', AppDir + '\php\php.ini', False) then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  try
    DownloadTemporaryFile('https://curl.se/ca/cacert.pem', 'cacert.pem', '', nil);
    DownloadSuccess := True;
  except
    DownloadSuccess := False;
  end;
  
  if not DownloadSuccess then
  begin
    MsgBox(ExpandConstant('{cm:DownloadPhpFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  if not FileCopy(TmpDir + '\cacert.pem', AppDir + '\php\cacert.pem', False) then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  PhpIniFile := AppDir + '\php\php.ini';
  if not LoadStringFromFile(PhpIniFile, PhpIniContentAnsi) then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  PhpIniContent := String(PhpIniContentAnsi);
  
  if StringChangeEx(PhpIniContent, ';extension=openssl', 'extension=openssl', True) <= 0 then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  if StringChangeEx(PhpIniContent, ';extension=curl', 'extension=curl', True) <= 0 then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  if StringChangeEx(PhpIniContent, ';extension_dir = "ext"', 'extension_dir = "ext"', True) <= 0 then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  if StringChangeEx(PhpIniContent, 'memory_limit = 128M', 'memory_limit = 4G', True) <= 0 then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  if StringChangeEx(PhpIniContent, ';curl.cainfo =', 'curl.cainfo = "' + AppDir + '\php\cacert.pem"', True) <= 0 then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  if not SaveStringToFile(PhpIniFile, AnsiString(PhpIniContent), False) then
  begin
    MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
    ExitProcess(1);
  end;
  
  if not PathExistsInPath(BinPath) then
  begin
    if not RegWriteExpandStringValue(GetTargetPathSection(), GetTargetPathKey(), 'Path', GetPathValue() + ';' + BinPath) then
    begin
      MsgBox(ExpandConstant('{cm:CopyFileFailed}'), mbError, MB_OK);
      ExitProcess(1);
    end;
  end;
  
  DownloadPhpPage.Description := ExpandConstant('{cm:DownloadFinished}');
  WizardForm.NextButton.Enabled := True;
  WizardForm.Update;
end;

procedure CurPageChanged(CurrentPageID: Integer);
begin
  case CurrentPageId of
    DownloadPhpPage.ID: DownloadPhpPageHandler();
    wpFinished: FinishedPageHandler();
  end;
end;

procedure InitializeUninstallProgressForm();
  var BinPath: String;
  var Path: String;
  var NewPath: String;
begin
  BinPath := ExpandConstant('{app}') + '\bin';
  
  if PathExistsInPath(BinPath) then
  begin
    Path := GetPathValue();
    NewPath := Copy(Path, 1, Length(Path) - Length(BinPath) - 1);
    RegWriteExpandStringValue(GetTargetPathSection(), GetTargetPathKey(), 'Path', NewPath)
  end;
end;
