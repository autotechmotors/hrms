' Fully hidden process. Args: [0]=working directory, [1]=executable, [2...]=arguments
If WScript.Arguments.Count < 2 Then WScript.Quit 1
Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = WScript.Arguments.Item(0)
exe = WScript.Arguments.Item(1)
args = ""
For i = 2 To WScript.Arguments.Count - 1
  args = args & " " & WScript.Arguments.Item(i)
Next
' 0 = hidden window, False = do not wait
sh.Run """" & exe & """" & args, 0, False
