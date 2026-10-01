<#
Builds the Windows-ready Inkspire app bundle on your own PC: the same recipe as .github/workflows/release.yml,
for installs without a GitHub release (an offline shop PC, a test build). Run from the repository root with
PHP 8.4, Composer and Node 22+ on PATH:

    .\tools\build-bundle.ps1 -Tag v1.2.3

It builds from a clean export of the last COMMIT in a temporary folder, so your working copy (dev vendor/,
node_modules, uncommitted edits) is never touched. Output: dist\inkspire-app-<tag>.zip and .sha256.
Install it with Inkspire Control Panel: Updates > Install from a file, or the setup wizard's
"Use an app bundle (.zip)".
#>
param(
    [string]$Tag = "v0.0.0-local",
    [string]$OutDir = "dist"
)
$ErrorActionPreference = "Stop"

foreach ($tool in "git", "php", "composer", "node", "npm", "tar") {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { throw "'$tool' was not found on PATH." }
}
$phpVersion = (php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
if ([version]$phpVersion -lt [version]"8.3") { throw "PHP $phpVersion found; Inkspire needs 8.3 or newer." }

$repo = (git rev-parse --show-toplevel).Trim()
$commit = (git rev-parse HEAD).Trim()
$work = Join-Path ([IO.Path]::GetTempPath()) ("inkspire-bundle-" + [guid]::NewGuid().ToString("N").Substring(0, 8))
New-Item -ItemType Directory -Path $work | Out-Null
$out = if ([IO.Path]::IsPathRooted($OutDir)) { $OutDir } else { Join-Path $repo $OutDir }
New-Item -ItemType Directory -Force -Path $out | Out-Null

try {
    Write-Host "Exporting commit $($commit.Substring(0,7)) ..."
    cmd /c "git -C `"$repo`" archive --format=tar HEAD | tar -xf - -C `"$work`""
    if ($LASTEXITCODE -ne 0) { throw "git archive failed" }

    Push-Location $work
    Write-Host "composer install --no-dev ..."
    composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
    if ($LASTEXITCODE -ne 0) { throw "composer install failed" }

    Write-Host "Building the frontend ..."
    Copy-Item .env.example .env
    (Get-Content .env) -replace '^APP_NAME=.*', 'APP_NAME=Inkspire' | Set-Content .env -Encoding ascii
    php artisan key:generate --force
    if ($LASTEXITCODE -ne 0) { throw "key:generate failed" }
    npm ci --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { npm install --no-audit --no-fund }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw "npm run build failed" }
    if (-not (Test-Path public\build\manifest.json)) { throw "The build produced no public\build\manifest.json" }
    Pop-Location

    # Drop everything the running app does not need.
    $drop = ".git", ".github", ".claude", ".planning", ".env", "node_modules", "tests", "demo", "dist", "storage", "tools",
            ".mcp.json", ".npmrc", ".editorconfig", ".gitignore", ".gitattributes", "AGENTS.md", "CLAUDE.md", "README.md", "boost.json",
            "components.json", "phpstan.neon", "phpunit.xml", "pint.json", "pnpm-workspace.yaml", "tsconfig.json", "vite.config.ts",
            "package.json", "package-lock.json"
    foreach ($name in $drop) {
        $p = Join-Path $work $name
        if (Test-Path $p) { Remove-Item $p -Recurse -Force }
    }
    Get-ChildItem (Join-Path $work "bootstrap\cache") -Filter *.php -ErrorAction SilentlyContinue | Remove-Item -Force
    ('{"tag":"' + $Tag + '","commit":"' + $commit + '","built":"' + (Get-Date).ToUniversalTime().ToString("s") + 'Z"}') |
        Set-Content (Join-Path $work "bundle.json") -Encoding ascii

    $zipPath = Join-Path $out "inkspire-app-$Tag.zip"
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    Write-Host "Zipping to $zipPath ..."
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [IO.Compression.ZipFile]::Open($zipPath, [IO.Compression.ZipArchiveMode]::Create)
    try {
        $prefix = $work.TrimEnd('\') + '\'
        foreach ($file in Get-ChildItem $work -Recurse -File -Force) {
            # forward slashes and ONE top-level folder: what Inkspire Control Panel expects
            $entry = "inkspire/" + $file.FullName.Substring($prefix.Length).Replace('\', '/')
            [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entry, [IO.Compression.CompressionLevel]::Optimal)
        }
    } finally { $zip.Dispose() }

    $hash = (Get-FileHash $zipPath -Algorithm SHA256).Hash.ToLower()
    "$hash  inkspire-app-$Tag.zip" | Set-Content "$zipPath.sha256" -Encoding ascii
    "{0}  {1:N1} MB" -f (Split-Path $zipPath -Leaf), ((Get-Item $zipPath).Length / 1MB)
} finally {
    if ((Get-Location).Path -like "$work*") { Pop-Location }
    Remove-Item $work -Recurse -Force -ErrorAction SilentlyContinue
}
