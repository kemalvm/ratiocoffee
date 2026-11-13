<#
  Ratio POS Printer Bridge
  ------------------------
  Bridge lokal yang berjalan di PC kasir agar website POS dapat:
    - menemukan printer yang benar-benar terpasang (spooler Windows + port COM),
    - mengirim data RAW ESC/POS ke printer yang kompatibel,
    - mencetak lewat driver Windows (rendering teks) untuk printer non-ESC/POS.

  Hanya mendengarkan di http://localhost:<Port>/ (tidak dapat diakses dari jaringan lain).
  Hanya origin yang diizinkan (AllowedOrigins + localhost) yang boleh memanggil API.

  Jalankan:  powershell -NoProfile -ExecutionPolicy Bypass -File ratio-printer-bridge.ps1
  Opsi    :  -Port 17777 -AllowedOrigins "https://ratiocoffee.my.id,https://cabang2.contoh.id"
#>
param(
    [int]$Port = 17777,
    [string]$AllowedOrigins = 'https://ratiocoffee.my.id,https://www.ratiocoffee.my.id'
)

$ErrorActionPreference = 'Stop'
$BridgeVersion = '1.0.0'
$allowedList = @($AllowedOrigins.Split(',') | ForEach-Object { $_.Trim().TrimEnd('/') } | Where-Object { $_ })

Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @"
using System;
using System.ComponentModel;
using System.Drawing;
using System.Drawing.Printing;
using System.Runtime.InteropServices;

namespace RatioBridge {
    public static class RawPrinter {
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        public class DOCINFO {
            [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
            [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
            [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
        }
        [DllImport("winspool.drv", EntryPoint = "OpenPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]
        static extern bool OpenPrinter(string name, out IntPtr h, IntPtr defaults);
        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool ClosePrinter(IntPtr h);
        [DllImport("winspool.drv", EntryPoint = "StartDocPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]
        static extern int StartDocPrinter(IntPtr h, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFO di);
        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool EndDocPrinter(IntPtr h);
        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool StartPagePrinter(IntPtr h);
        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool EndPagePrinter(IntPtr h);
        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool WritePrinter(IntPtr h, IntPtr buf, int count, out int written);

        public static int Send(string printer, byte[] data, string docName) {
            IntPtr h;
            if (!OpenPrinter(printer, out h, IntPtr.Zero)) throw new Win32Exception(Marshal.GetLastWin32Error(), "OpenPrinter gagal");
            IntPtr buf = IntPtr.Zero;
            try {
                DOCINFO di = new DOCINFO();
                di.pDocName = docName;
                di.pDataType = "RAW";
                if (StartDocPrinter(h, 1, di) == 0) throw new Win32Exception(Marshal.GetLastWin32Error(), "StartDocPrinter gagal (driver mungkin tidak menerima RAW)");
                try {
                    if (!StartPagePrinter(h)) throw new Win32Exception(Marshal.GetLastWin32Error(), "StartPagePrinter gagal");
                    buf = Marshal.AllocCoTaskMem(data.Length);
                    Marshal.Copy(data, 0, buf, data.Length);
                    int written;
                    if (!WritePrinter(h, buf, data.Length, out written)) throw new Win32Exception(Marshal.GetLastWin32Error(), "WritePrinter gagal");
                    EndPagePrinter(h);
                    return written;
                } finally { EndDocPrinter(h); }
            } finally {
                if (buf != IntPtr.Zero) Marshal.FreeCoTaskMem(buf);
                ClosePrinter(h);
            }
        }
    }

    public static class TextPrinter {
        public static void Print(string printer, string[] texts, int[] aligns, int[] styles,
                                 float paperWidthMm, float printableWidthMm, int charsPerLine, string docName) {
            PrintDocument doc = new PrintDocument();
            doc.PrinterSettings.PrinterName = printer;
            if (!doc.PrinterSettings.IsValid) throw new Exception("Printer tidak valid: " + printer);
            doc.DocumentName = docName;
            doc.OriginAtMargins = false;
            doc.DefaultPageSettings.Margins = new Margins(0, 0, 0, 0);

            float widthPt = printableWidthMm / 25.4f * 72f;
            float baseSize = widthPt / (Math.Max(charsPerLine, 10) * 0.6f);
            if (baseSize < 5f) baseSize = 5f;
            if (baseSize > 14f) baseSize = 14f;
            float widthHi = printableWidthMm / 25.4f * 100f;

            // Perkiraan tinggi kertas (driver boleh mengabaikan ukuran kustom; pagination tetap aman).
            float lineHi = baseSize * 1.3f / 72f * 100f;
            float estHeight = 0;
            for (int i = 0; i < texts.Length; i++) estHeight += ((styles[i] & 2) != 0) ? lineHi * 1.7f : lineHi;
            try {
                int w = (int)Math.Round(paperWidthMm / 25.4f * 100f);
                int hgt = (int)Math.Round(estHeight + lineHi * 4);
                doc.DefaultPageSettings.PaperSize = new PaperSize("Receipt", w, Math.Max(hgt, 100));
            } catch { }

            int index = 0;
            doc.PrintPage += (s, e) => {
                Graphics g = e.Graphics;
                float y = 0;
                float pageH = e.PageBounds.Height;
                while (index < texts.Length) {
                    bool bold = (styles[index] & 1) != 0;
                    bool large = (styles[index] & 2) != 0;
                    float size = large ? baseSize * 1.6f : baseSize;
                    using (Font f = new Font("Consolas", size, (bold || large) ? FontStyle.Bold : FontStyle.Regular, GraphicsUnit.Point))
                    using (StringFormat sf = new StringFormat()) {
                        sf.Alignment = aligns[index] == 1 ? StringAlignment.Center : (aligns[index] == 2 ? StringAlignment.Far : StringAlignment.Near);
                        string t = string.IsNullOrEmpty(texts[index]) ? " " : texts[index];
                        SizeF sz = g.MeasureString(t, f, (int)widthHi, sf);
                        float h = Math.Max(sz.Height, f.GetHeight(g));
                        if (y + h > pageH && y > 0) { e.HasMorePages = true; return; }
                        g.DrawString(t, f, Brushes.Black, new RectangleF(0, y, widthHi, h), sf);
                        y += h;
                    }
                    index++;
                }
                e.HasMorePages = false;
            };
            doc.Print();
        }
    }
}
"@

function New-BridgeError([int]$Status, [string]$Code, [string]$Message) {
    $ex = New-Object System.Exception $Message
    $ex.Data['status'] = $Status
    $ex.Data['code'] = $Code
    return $ex
}

function Test-OriginAllowed([string]$Origin) {
    if (-not $Origin) { return $true }   # bukan permintaan lintas-origin dari browser
    $o = $Origin.TrimEnd('/')
    if ($allowedList -contains $o) { return $true }
    if ($o -match '^https?://(localhost|127\.0\.0\.1)(:\d+)?$') { return $true }
    if ($o -match '^https?://([a-z0-9-]+\.)?ratiocoffee\.my\.id(:\d+)?$') { return $true }
    if ($o -match '^https?://(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)(:\d+)?$') { return $true }
    return $false
}

function Set-Cors($Ctx) {
    $origin = $Ctx.Request.Headers['Origin']
    if ($origin -and (Test-OriginAllowed $origin)) {
        $h = $Ctx.Response.Headers
        $h.Add('Access-Control-Allow-Origin', $origin)
        $h.Add('Vary', 'Origin')
        $h.Add('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        $h.Add('Access-Control-Allow-Headers', 'Content-Type')
        $h.Add('Access-Control-Allow-Private-Network', 'true')
        $h.Add('Access-Control-Max-Age', '600')
    }
}

function Send-Json($Ctx, [int]$Status, $Body) {
    Set-Cors $Ctx
    $json = $Body | ConvertTo-Json -Depth 6 -Compress
    $buf = [System.Text.Encoding]::UTF8.GetBytes($json)
    $res = $Ctx.Response
    $res.StatusCode = $Status
    $res.ContentType = 'application/json; charset=utf-8'
    $res.ContentLength64 = $buf.Length
    $res.OutputStream.Write($buf, 0, $buf.Length)
    $res.OutputStream.Close()
}

function Read-JsonBody($Req) {
    if ($Req.ContentLength64 -gt 2MB) { throw (New-BridgeError 413 'PAYLOAD_TOO_LARGE' 'Payload terlalu besar.') }
    $sr = New-Object System.IO.StreamReader($Req.InputStream, [System.Text.Encoding]::UTF8)
    try { $txt = $sr.ReadToEnd() } finally { $sr.Close() }
    if (-not $txt) { throw (New-BridgeError 400 'EMPTY_BODY' 'Body permintaan kosong.') }
    return ($txt | ConvertFrom-Json)
}

function Get-SerialFriendlyMap {
    $map = @{}
    try {
        Get-CimInstance Win32_PnPEntity -Filter "Name LIKE '%(COM%'" | ForEach-Object {
            if ($_.Name -match '\((COM\d+)\)') { $map[$Matches[1]] = [string]$_.Name }
        }
    } catch { }
    return $map
}

function Get-ConnectionType([string]$PortName, $SerialMap) {
    if ($PortName -match '^USB\d+') { return 'usb' }
    if ($PortName -match '^(COM\d+):?$') {
        $friendly = $SerialMap[$Matches[1]]
        if ($friendly -match 'Bluetooth') { return 'bluetooth' }
        if ($friendly -match 'USB') { return 'usb' }
        return 'serial'
    }
    if ($PortName -match 'BTH|Bluetooth') { return 'bluetooth' }
    if ($PortName -match '^(IP_|TCP|WSD|\d+\.\d+\.\d+\.\d+)') { return 'network' }
    return 'other'
}

function Get-SpoolerStatus($P) {
    if ($P.WorkOffline) { return 'offline' }
    switch ([int]$P.PrinterStatus) {
        3 { return 'ready' }      # Idle
        4 { return 'ready' }      # Printing
        5 { return 'ready' }      # Warmup
        6 { return 'error' }      # Stopped printing
        7 { return 'offline' }
        default { return 'unknown' }
    }
}

function Get-DiscoveredPrinters {
    $serialMap = Get-SerialFriendlyMap
    $list = New-Object System.Collections.ArrayList

    foreach ($p in @(Get-CimInstance Win32_Printer)) {
        $port = [string]$p.PortName
        $driver = [string]$p.DriverName
        $virtual = ($port -match '^(PORTPROMPT:|SHRFAX:|nul:|FILE:)$') -or ($driver -match 'Print To PDF|XPS|OneNote|Fax|PDF')
        [void]$list.Add([ordered]@{
            id             = 'win:' + $p.Name
            kind           = 'windows'
            name           = [string]$p.Name
            label          = [string]$p.Name
            connection     = (Get-ConnectionType $port $serialMap)
            port           = $port
            driver         = $driver
            status         = (Get-SpoolerStatus $p)
            statusSource   = 'windows-spooler'
            isDefault      = [bool]$p.Default
            virtual        = [bool]$virtual
            escposHint     = [bool](($driver -match '(?i)\bPOS\b|POS-?\d|Thermal|Receipt|Text Only') -and ($driver -notmatch '(?i)ESC/P-R|ESC/P2|PCL|PostScript'))
            supportsRaw    = $true
            supportsDriver = $true
        })
    }

    foreach ($com in @([System.IO.Ports.SerialPort]::GetPortNames() | Sort-Object -Unique)) {
        $friendly = $serialMap[$com]
        $conn = 'serial'
        if ($friendly -match 'Bluetooth') { $conn = 'bluetooth' } elseif ($friendly -match 'USB') { $conn = 'usb' }
        $label = $com
        if ($friendly) { $label = $friendly }
        [void]$list.Add([ordered]@{
            id             = 'com:' + $com
            kind           = 'serial'
            name           = $com
            label          = $label
            connection     = $conn
            port           = $com
            driver         = ''
            status         = 'available'
            statusSource   = 'port-present'
            isDefault      = $false
            virtual        = $false
            escposHint     = $false
            supportsRaw    = $true
            supportsDriver = $false
        })
    }
    return , $list.ToArray()
}

function Assert-TargetAvailable($Target) {
    if (-not $Target -or -not $Target.name) { throw (New-BridgeError 400 'INVALID_TARGET' 'Target printer tidak valid.') }
    $name = [string]$Target.name
    if ($Target.kind -eq 'windows') {
        $p = Get-CimInstance Win32_Printer | Where-Object { $_.Name -eq $name } | Select-Object -First 1
        if (-not $p) { throw (New-BridgeError 404 'PRINTER_NOT_FOUND' "Printer '$name' tidak ditemukan di Windows perangkat ini.") }
        return
    }
    if ($Target.kind -eq 'serial') {
        if ($name -match '(COM\d+)') { $name = $Matches[1]; $Target.name = $name }
        if (-not (@([System.IO.Ports.SerialPort]::GetPortNames()) -contains $name)) {
            throw (New-BridgeError 404 'PRINTER_NOT_FOUND' "Port $name tidak tersedia. Pastikan printer tersambung/terpasang (paired).")
        }
        return
    }
    throw (New-BridgeError 400 'INVALID_TARGET' "Jenis target '$($Target.kind)' tidak didukung.")
}

function Invoke-RawPrint($Target, [byte[]]$Bytes) {
    Assert-TargetAvailable $Target
    if ($Target.kind -eq 'windows') {
        [void][RatioBridge.RawPrinter]::Send([string]$Target.name, $Bytes, 'Ratio POS Receipt')
        return
    }
    $baud = 9600
    if ($Target.baudRate) { $baud = [int]$Target.baudRate }
    $sp = New-Object System.IO.Ports.SerialPort -ArgumentList ([string]$Target.name), $baud
    $sp.WriteTimeout = 8000
    $sp.Handshake = [System.IO.Ports.Handshake]::None
    $sp.DtrEnable = $true
    $sp.RtsEnable = $true
    try {
        $sp.Open()
        $sp.Write($Bytes, 0, $Bytes.Length)
        $deadline = (Get-Date).AddSeconds(10)
        while ($sp.BytesToWrite -gt 0 -and (Get-Date) -lt $deadline) { Start-Sleep -Milliseconds 50 }
        Start-Sleep -Milliseconds 300
    } finally {
        if ($sp.IsOpen) { $sp.Close() }
        $sp.Dispose()
    }
}

function Invoke-TextPrint($Body) {
    $t = $Body.target
    Assert-TargetAvailable $t
    if ($t.kind -ne 'windows') { throw (New-BridgeError 400 'DRIVER_NOT_AVAILABLE' 'Mode driver hanya tersedia untuk printer yang terpasang di Windows.') }
    $lines = @($Body.lines)
    $texts = [string[]]@($lines | ForEach-Object { [string]$_.text })
    $aligns = [int[]]@($lines | ForEach-Object { if ($_.align -eq 'center') { 1 } elseif ($_.align -eq 'right') { 2 } else { 0 } })
    $styles = [int[]]@($lines | ForEach-Object { $s = 0; if ($_.bold) { $s = $s -bor 1 }; if ($_.size -eq 'large') { $s = $s -bor 2 }; $s })
    $paper = 58.0; if ($Body.paperWidthMm) { $paper = [single]$Body.paperWidthMm }
    $printable = $paper - 10; if ($Body.printableWidthMm) { $printable = [single]$Body.printableWidthMm }
    $cpl = 32; if ($Body.charsPerLine) { $cpl = [int]$Body.charsPerLine }
    [RatioBridge.TextPrinter]::Print([string]$t.name, $texts, $aligns, $styles, [single]$paper, [single]$printable, $cpl, 'Ratio POS Receipt')
}

function Invoke-Request($Ctx) {
    $req = $Ctx.Request
    $path = $req.Url.AbsolutePath.TrimEnd('/').ToLower()
    if (-not $path) { $path = '/' }
    $origin = $req.Headers['Origin']

    if (-not (Test-OriginAllowed $origin)) {
        Send-Json $Ctx 403 @{ ok = $false; code = 'ORIGIN_NOT_ALLOWED'; message = "Origin $origin tidak diizinkan. Jalankan bridge dengan -AllowedOrigins `"$origin`"." }
        return
    }
    if ($req.HttpMethod -eq 'OPTIONS') {
        Set-Cors $Ctx
        $Ctx.Response.StatusCode = 204
        $Ctx.Response.Close()
        return
    }

    switch ($path) {
        '/health' {
            Send-Json $Ctx 200 @{ ok = $true; name = 'ratio-printer-bridge'; version = $BridgeVersion; host = $env:COMPUTERNAME }
            return
        }
        '/printers' {
            $printers = Get-DiscoveredPrinters
            Send-Json $Ctx 200 @{ ok = $true; host = $env:COMPUTERNAME; printers = @($printers) }
            return
        }
        '/print/raw' {
            if ($req.HttpMethod -ne 'POST') { throw (New-BridgeError 405 'METHOD_NOT_ALLOWED' 'Gunakan POST.') }
            $body = Read-JsonBody $req
            if (-not $body.data_base64) { throw (New-BridgeError 400 'NO_DATA' 'data_base64 wajib diisi.') }
            $bytes = [System.Convert]::FromBase64String([string]$body.data_base64)
            Invoke-RawPrint $body.target $bytes
            Send-Json $Ctx 200 @{ ok = $true; bytes = $bytes.Length }
            return
        }
        '/print/text' {
            if ($req.HttpMethod -ne 'POST') { throw (New-BridgeError 405 'METHOD_NOT_ALLOWED' 'Gunakan POST.') }
            $body = Read-JsonBody $req
            Invoke-TextPrint $body
            Send-Json $Ctx 200 @{ ok = $true }
            return
        }
        default {
            Send-Json $Ctx 404 @{ ok = $false; code = 'NOT_FOUND'; message = 'Endpoint tidak dikenal.' }
            return
        }
    }
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://localhost:$Port/")
try {
    $listener.Start()
} catch {
    Write-Host "Gagal membuka port $Port. Kemungkinan bridge sudah berjalan atau port dipakai aplikasi lain." -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

Write-Host "Ratio POS Printer Bridge v$BridgeVersion berjalan di http://localhost:$Port/" -ForegroundColor Green
Write-Host ("Origin diizinkan: " + ($allowedList -join ', ') + ' (+ localhost)')
Write-Host 'Biarkan jendela ini terbuka selama kasir beroperasi. Tekan Ctrl+C untuk berhenti.'

try {
    while ($listener.IsListening) {
        $task = $listener.GetContextAsync()
        while (-not $task.AsyncWaitHandle.WaitOne(500)) { }
        $ctx = $task.GetAwaiter().GetResult()
        try {
            Invoke-Request $ctx
        } catch {
            $ex = $_.Exception
            $status = 500; $code = 'BRIDGE_ERROR'
            if ($ex.Data -and $ex.Data['status']) { $status = [int]$ex.Data['status']; $code = [string]$ex.Data['code'] }
            $msg = $ex.Message
            if ($ex.InnerException) { $msg = $ex.InnerException.Message }
            Write-Host ("[{0}] {1} {2}: {3}" -f (Get-Date -Format 'HH:mm:ss'), $ctx.Request.HttpMethod, $ctx.Request.Url.AbsolutePath, $msg) -ForegroundColor Yellow
            try { Send-Json $ctx $status @{ ok = $false; code = $code; message = $msg } } catch { }
        }
    }
} finally {
    $listener.Stop()
    $listener.Close()
}
