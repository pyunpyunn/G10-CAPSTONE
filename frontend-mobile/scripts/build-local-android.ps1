param(
    [Parameter(Mandatory = $true)][ValidateRange(1, 2147483647)][int]$VersionCode,
    [string]$SigningJson = "$env:LOCALAPPDATA\resqperation-build-private\signing.json",
    [string]$JavaHome = 'C:\backend\tools\resq-android\jdk-17.0.20.1+1',
    [string]$AndroidSdk = 'C:\backend\tools\resq-android\sdk'
)
$ErrorActionPreference = 'Stop'
$project = Split-Path $PSScriptRoot -Parent
if (!(Test-Path -LiteralPath $SigningJson)) { throw 'Existing release signing credentials are required.' }
$env:JAVA_HOME = $JavaHome
$env:ANDROID_HOME = $AndroidSdk
$env:ANDROID_SDK_ROOT = $AndroidSdk
$env:Path = "$JavaHome\bin;$env:Path"
$env:RESQ_ANDROID_SIGNING_JSON = (Resolve-Path -LiteralPath $SigningJson).Path
$env:RESQ_ANDROID_VERSION_CODE = "$VersionCode"
$env:RESQ_ANDROID_CXX_DIR = Join-Path (Split-Path $AndroidSdk -Parent) 'cxx'
$env:CI = '1'
$env:NODE_ENV = 'production'
Push-Location $project
try {
    $production = (Get-Content eas.json -Raw | ConvertFrom-Json).build.production.env
    $production.PSObject.Properties | ForEach-Object { [Environment]::SetEnvironmentVariable($_.Name, $_.Value, 'Process') }
    & npx.cmd expo prebuild --platform android --no-install
    if ($LASTEXITCODE -ne 0) { throw 'Android prebuild failed.' }
    @'
import groovy.json.JsonSlurper
def credentialsPath = System.getenv('RESQ_ANDROID_SIGNING_JSON')
if (!credentialsPath) throw new GradleException('Release signing JSON is required')
def credentials = new JsonSlurper().parse(new File(credentialsPath))
android {
    externalNativeBuild { cmake { buildStagingDirectory new File(System.getenv('RESQ_ANDROID_CXX_DIR')) } }
    signingConfigs {
        resqRelease {
            storeFile new File(credentials.storeFile)
            storePassword credentials.storePassword
            keyAlias credentials.keyAlias
            keyPassword credentials.keyPassword
        }
    }
    defaultConfig {
        versionCode Integer.parseInt(System.getenv('RESQ_ANDROID_VERSION_CODE'))
        externalNativeBuild { cmake { arguments '-DCMAKE_OBJECT_PATH_MAX=240' } }
    }
    buildTypes { release { signingConfig signingConfigs.resqRelease } }
}
'@ | Set-Content android/app/local-release.gradle -Encoding ascii
    $gradleFile = Join-Path $project 'android/app/build.gradle'
    $gradle = Get-Content $gradleFile -Raw
    if (!$gradle.Contains("apply from: file('local-release.gradle')")) {
        Add-Content $gradleFile "`napply from: file('local-release.gradle')"
    }
    Push-Location android
    try {
        & .\gradlew.bat :app:assembleRelease --no-daemon --max-workers=2 --console=plain --info '-Dorg.gradle.internal.http.connectionTimeout=30000' '-Dorg.gradle.internal.http.socketTimeout=30000'
        if ($LASTEXITCODE -ne 0) { throw 'Release compilation failed.' }
    } finally { Pop-Location }
    New-Item -ItemType Directory -Force releases | Out-Null
    $output = Join-Path $project "releases/RESQPERATION-build$VersionCode.apk"
    Copy-Item android/app/build/outputs/apk/release/app-release.apk $output
    $signature = & "$AndroidSdk\build-tools\36.0.0\apksigner.bat" verify --verbose --print-certs $output
    if ($LASTEXITCODE -ne 0) { throw 'APK signature verification failed.' }
    $signature | Write-Output
    $expectedCertificate = (Get-Content $SigningJson -Raw | ConvertFrom-Json).sha256 -replace ':', ''
    $actualCertificate = (($signature | Select-String '^Signer #1 certificate SHA-256 digest:').Line -split ': ', 2)[1]
    if (!$expectedCertificate -or $actualCertificate -ine $expectedCertificate) { throw 'APK does not match the existing release certificate.' }
    $metadata = & "$AndroidSdk\build-tools\36.0.0\aapt.exe" dump badging $output
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect APK metadata.' }
    if (!($metadata -match "package: name='com.kathlnbarro.resqperationmobile' versionCode='$VersionCode'")) { throw 'Unexpected APK identity or version code.' }
    if ($metadata -match '^application-debuggable') { throw 'APK is debuggable.' }
    $sha256 = [System.Security.Cryptography.SHA256]::Create()
    try {
        $digest = [BitConverter]::ToString($sha256.ComputeHash([System.IO.File]::ReadAllBytes($output))).Replace('-', '')
    } finally { $sha256.Dispose() }
    Write-Host "SHA-256: $digest"
    Write-Host "Release APK: $output"
} finally { Pop-Location }
