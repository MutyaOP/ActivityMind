# Барлық .drawio файлдарын png\ папкасына экспорттайды (draw.io Desktop керек)
$drawio = "C:\Program Files\draw.io\draw.io.exe"
$here = $PSScriptRoot
New-Item -ItemType Directory -Force "$here\png" | Out-Null
Get-ChildItem "$here\*.drawio" | ForEach-Object {
    & $drawio -x -f png -s 2 -b 10 -o "$here\png\$($_.BaseName).png" $_.FullName
}
