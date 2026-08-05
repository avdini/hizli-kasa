param (
    [string]$CommitHash = "HEAD"
)

$ErrorActionPreference = "Stop"

# Resolve the commit hash before switching branches so "HEAD" points to the correct starting commit
$ResolvedCommit = (git rev-parse $CommitHash).Trim()

# Get current branch to return to it later
$CurrentBranch = (git branch --show-current).Trim()

Write-Host "Fetching latest updates from public remote..." -ForegroundColor Cyan
git fetch public

# Generate a random suffix for the temp branch
$RandomSuffix = Get-Random
$TempBranch = "temp-public-patch-$RandomSuffix"

$PrivateItems = @("src-driver", "scripts", ".agents", ".codegraph", "docs/superpowers", "AGENTS.md", "scratch", ".gitattributes")

try {
    Write-Host "Creating temporary branch from public/master..." -ForegroundColor Cyan
    git checkout -b $TempBranch public/master
    
    Write-Host "Cherry-picking commit $ResolvedCommit..." -ForegroundColor Cyan
    $gitResult = & git cherry-pick $ResolvedCommit 2>&1
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Conflict detected on cherry-pick. Filtering out private-only files..." -ForegroundColor Yellow
        foreach ($item in $PrivateItems) {
            & git rm -rf $item --ignore-unmatch 2>&1 | Out-Null
        }
        & git add -A
        $env:GIT_EDITOR = 'true'
        & git cherry-pick --continue --no-edit 2>&1 | Out-Null
    }
    
    # Ensure private-only items are never in public patch
    $hasPrivateItems = $false
    foreach ($item in $PrivateItems) {
        if (Test-Path $item) {
            Write-Host "Filtering out private-only '$item' from public patch..." -ForegroundColor Yellow
            & git rm -rf $item --ignore-unmatch 2>&1 | Out-Null
            $hasPrivateItems = $true
        }
    }

    # Clean up empty docs directory if superpowers was the only item inside it
    if ((Test-Path "docs") -and ((Get-ChildItem -Path "docs" -Recurse | Measure-Object).Count -eq 0)) {
        & git rm -rf docs --ignore-unmatch 2>&1 | Out-Null
        $hasPrivateItems = $true
    }

    if ($hasPrivateItems) {
        & git commit --amend --no-edit --allow-empty 2>&1 | Out-Null
    }

    Write-Host "Pushing changes to public master..." -ForegroundColor Cyan
    & git push public "${TempBranch}:master"
    
    Write-Host "Successfully patched public repository!" -ForegroundColor Green
}
catch {
    Write-Host "An error occurred: $_" -ForegroundColor Red
    $Status = git status
    if ($Status -match "cherry-pick") {
        Write-Host "Aborting cherry-pick due to unresolvable conflicts..." -ForegroundColor Yellow
        git cherry-pick --abort
    }
}
finally {
    Write-Host "Cleaning up... Switching back to $CurrentBranch" -ForegroundColor Cyan
    git checkout $CurrentBranch
    
    # Delete temporary branch
    $Branches = git branch
    if ($Branches -match $TempBranch) {
        git branch -D $TempBranch
    }
}

