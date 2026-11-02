' Fully hidden watchdog — no CMD on screen / taskbar.
Set sh = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
dir = fso.GetParentFolderName(WScript.ScriptFullName)
sh.Run "cmd /c """ & dir & "\hrms-watchdog.bat""", 0, False
