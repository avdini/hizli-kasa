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

try {
    Write-Host "Creating temporary branch from public/master..." -ForegroundColor Cyan
    git checkout -b $TempBranch public/master
    
    Write-Host "Cherry-picking commit $ResolvedCommit..." -ForegroundColor Cyan
    git cherry-pick $ResolvedCommit
    
    # Strip private-only src-driver folder if present in public patch
    if (Test-Path "src-driver") {
        Write-Host "Filtering out private-only 'src-driver' folder from public patch..." -ForegroundColor Yellow
        git rm -rf src-driver --ignore-unmatch | Out-Null
        git commit --amend --no-edit --allow-empty | Out-Null
    }

    Write-Host "Pushing changes to public master..." -ForegroundColor Cyan
    git push public "${TempBranch}:master"
    
    Write-Host "Successfully patched public repository!" -ForegroundColor Green
}
catch {
    Write-Host "An error occurred: $_" -ForegroundColor Red
    # If in the middle of a cherry-pick, abort it
    $Status = git status
    if ($Status -match "cherry-pick") {
        Write-Host "Aborting cherry-pick due to conflicts..." -ForegroundColor Yellow
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
