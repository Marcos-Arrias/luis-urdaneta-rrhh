Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' Obtiene la ruta de la carpeta donde está guardado este script
scriptFolder = fso.GetParentFolderName(WScript.ScriptFullName)

' Inicia Apache en segundo plano
WshShell.Run """C:\xampp\apache\bin\httpd.exe""", 0, False

' Inicia MySQL en segundo plano
WshShell.Run """C:\xampp\mysql\bin\mysqld.exe""", 0, False

' Abre el archivo HTML de la misma carpeta en tu navegador predeterminado
' REEMPLAZA "index.html" por el nombre real de tu archivo
WshShell.Run """" & scriptFolder & "\index.html""", 1, False
