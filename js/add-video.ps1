param(
  [string]$Link,
  [string]$Title,
  [string]$Published,
  [string]$Description
)

$videosPath = Join-Path $PSScriptRoot "videos.json"

if (-not (Test-Path $videosPath)) {
  '{"videos":[]}' | Set-Content -Path $videosPath -Encoding UTF8
}

$jsonText = Get-Content -Path $videosPath -Raw -Encoding UTF8
$data = $jsonText | ConvertFrom-Json

if ($null -eq $data.videos) {
  $data | Add-Member -MemberType NoteProperty -Name "videos" -Value @()
}

if ([string]::IsNullOrWhiteSpace($Link)) {
  $Link = Read-Host "Video link (YouTube URL)"
}
if ([string]::IsNullOrWhiteSpace($Title)) {
  $Title = Read-Host "Title"
}
if ([string]::IsNullOrWhiteSpace($Published)) {
  $Published = Read-Host "Published date (YYYY-MM-DD, optional)"
}
if ([string]::IsNullOrWhiteSpace($Description)) {
  $Description = Read-Host "Description (optional)"
}

$newItem = [PSCustomObject]@{
  link = $Link.Trim()
  title = $Title.Trim()
  published = $Published.Trim()
  description = $Description.Trim()
}

$currentVideos = @($data.videos)
$updatedVideos = @($newItem) + $currentVideos

$output = [PSCustomObject]@{
  videos = $updatedVideos
}

$output | ConvertTo-Json -Depth 6 | Set-Content -Path $videosPath -Encoding UTF8

Write-Host "Added video to $videosPath"
Write-Host "Total videos:" $updatedVideos.Count
