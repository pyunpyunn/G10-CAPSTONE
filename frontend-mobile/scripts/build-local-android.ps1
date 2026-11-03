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
$env:RESQ_ANDROID_RELEASE = '1'
Push-Location $project
try {
    $production = (Get-Content eas.json -Raw | ConvertFrom-Json).build.production.env
    $apiUrls = @($production.EXPO_PUBLIC_API_BASE_URL) + @($production.EXPO_PUBLIC_API_FALLBACK_URLS -split ',' | Where-Object { $_ })
    if (!$production.RESQ_ANDROID_RELEASE -or !$production.EXPO_PUBLIC_API_BASE_URL) {
        throw 'Release configuration must enable secure Android release settings and provide an HTTPS API URL.'
    }
    $allowPrivateReleaseCleartext = $false
    foreach ($apiUrl in $apiUrls) {
        $parsedApiUrl = $null
        if (![Uri]::TryCreate($apiUrl.Trim(), [UriKind]::Absolute, [ref]$parsedApiUrl)) {
            throw "Release API URL is invalid: $apiUrl"
        }
        $octets = $parsedApiUrl.Host.Split('.') | ForEach-Object { $value = 0; if (![int]::TryParse($_, [ref]$value)) { -1 } else { $value } }
        $privateIpv4 = $octets.Count -eq 4 -and $octets -notcontains -1 -and $octets[0] -ge 0 -and $octets[0] -le 255 `
            -and $octets[1] -ge 0 -and $octets[1] -le 255 -and $octets[2] -ge 0 -and $octets[2] -le 255 -and $octets[3] -ge 0 -and $octets[3] -le 255 `
            -and ($octets[0] -eq 10 -or ($octets[0] -eq 192 -and $octets[1] -eq 168) `
                -or ($octets[0] -eq 172 -and $octets[1] -ge 16 -and $octets[1] -le 31) `
                -or ($octets[0] -eq 100 -and $octets[1] -ge 64 -and $octets[1] -le 127))
        if ($parsedApiUrl.Scheme -eq 'http' -and $privateIpv4) {
            $allowPrivateReleaseCleartext = $true
        }
        if ((($parsedApiUrl.Scheme -ne 'https') -and !(($parsedApiUrl.Scheme -eq 'http') -and $privateIpv4)) `
            -or !$parsedApiUrl.AbsolutePath.TrimEnd('/').EndsWith('/api/v1')) {
            throw "Release API URLs must use HTTPS or a private IPv4 HTTP address and end with /api/v1: $apiUrl"
        }
    }
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
    $expectedVersion = (Get-Content app.json -Raw | ConvertFrom-Json).expo.version
    if (!($metadata -match "package: name='com.kathlnbarro.resqperationmobile' versionCode='$VersionCode' versionName='$([regex]::Escape($expectedVersion))'")) { throw 'Unexpected APK identity or version.' }
    if ($metadata -match '^application-debuggable') { throw 'APK is debuggable.' }
    $manifest = & "$AndroidSdk\build-tools\36.0.0\aapt.exe" dump xmltree $output AndroidManifest.xml
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect the APK manifest.' }
    $manifestAllowsCleartext = [bool]($manifest -match 'android:usesCleartextTraffic.*\(type 0x12\)0xffffffff')
    if ($manifestAllowsCleartext -ne $allowPrivateReleaseCleartext) {
        throw 'Release APK cleartext policy does not match the configured private HTTP routes.'
    }
    $sha256 = [System.Security.Cryptography.SHA256]::Create()
    try {
        $digest = [BitConverter]::ToString($sha256.ComputeHash([System.IO.File]::ReadAllBytes($output))).Replace('-', '')
    } finally { $sha256.Dispose() }
    Write-Host "SHA-256: $digest"
    Write-Host "Release APK: $output"
} finally { Pop-Location }
