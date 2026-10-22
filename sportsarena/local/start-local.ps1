# Runs SportsArena on this computer with WordPress Playground: http://127.0.0.1:9400
# The site reads the plugin and theme straight from this repository, so any change
# to their files shows up after a browser refresh. The site (database, settings,
# uploads) is kept between runs. Keep this window open while you use the site.
#
# Start:  double-click start-local.cmd (or run this file in PowerShell).
# Reset:  start-local.cmd -Reset  (deletes the local site and creates it again).

param( [switch] $Reset )

$ErrorActionPreference = 'Stop'

if ( -not ( Get-Command node -ErrorAction SilentlyContinue ) ) {
	Write-Host 'Node.js is not installed. Install the LTS version from https://nodejs.org then run this again.' -ForegroundColor Red
	Read-Host 'Press Enter to close'
	exit 1
}

# Paths are relative to the sportsarena folder (WordPress Playground cannot read "D:" in --mount).
Set-Location ( Split-Path -Parent $PSScriptRoot )

$cliArgs = @(
	'-y', '@wp-playground/cli@3.1.60', 'start',
	'--no-auto-mount',
	'--port=9400',
	'--php=8.3',
	'--mount=./sportsarena-core:/wordpress/wp-content/plugins/sportsarena-core',
	'--mount=./sportsarena-theme:/wordpress/wp-content/themes/sportsarena-theme',
	'--blueprint=./local/blueprint.json'
)
if ( $Reset ) {
	$cliArgs += '--reset'
}

Write-Host ''
Write-Host 'Starting SportsArena... the first run downloads WordPress and takes a few minutes.' -ForegroundColor Green
Write-Host 'Site:  http://127.0.0.1:9400'
Write-Host 'Admin: http://127.0.0.1:9400/wp-admin   (user: admin  password: password)'
Write-Host 'Keep this window open. Press Ctrl+C to stop the site.'
Write-Host ''

& npx @cliArgs
if ( $LASTEXITCODE -ne 0 ) {
	Write-Host ''
	Write-Host 'The site stopped with an error. Take a screenshot of this window.' -ForegroundColor Red
	Read-Host 'Press Enter to close'
}
