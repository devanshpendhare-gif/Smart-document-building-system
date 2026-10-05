<?php
session_start();

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Document Builder</title>
    <link rel="stylesheet" href="home.css">
</head>
<body>
    <!-- Fixed top navbar with logout button -->
    <nav class="top-navbar">
        <form method="POST" action="home.php">
            <button type="submit" name="logout" class="logout-btn">Logout</button>
        </form>
    </nav>

    <div class="main">
        <div id="head">
            <h5>SMART DOCUMENT BUILDER</h5>
        </div>
        <div class="welcome-text" style="margin-top:20px;">
            <h2 style="color:#ffffff;">Welcome to,<?php echo htmlspecialchars($user_name); ?></h2>
            <?php if ($user_email): ?>
                <p style="color:#ffffff;"><?php echo htmlspecialchars($user_email); ?></p>
            <?php endif; ?>
        </div>

        <div class="text">
            <h1>Documents That</h1>
        </div>
        <div class="text1">
            <h1>Win Opportunities</h1>
        </div>

        <div class="p">
            <p>Professional résumés, cover letters, and bio-data — with live preview, three templates, and one-click PDF export.</p>
        </div>

        <div class="box">
            <div class="box1 card-clickable" id="card-resume" onclick="handleCardClick('resume')" role="button" tabindex="0" aria-label="Start building Resume / CV">
                <div class="icon icon-orange">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 11h20"/></svg>
                </div>
                <div class="boxhead"><p>RESUME / CV</p></div>
                <div class="boxtext"><p>Create a professional resume that gets shortlisted by top Indian and MNC companies.</p></div>
                <div class="footer"><p>Start building</p></div>
            </div>

            <div class="box2 card-clickable" id="card-cover" onclick="handleCardClick('cover-letter')" role="button" tabindex="0" aria-label="Start building Cover Letter">
                <div class="icon icon-blue">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="boxhead"><p>COVER LETTER</p></div>
                <div class="boxtext"><p>Write a strong cover letter that makes the right first impression on recruiters.</p></div>
                <div class="footer"><p>Start building</p></div>
            </div>

            <div class="box3 card-clickable" id="card-biodata" onclick="handleCardClick('bio-data')" role="button" tabindex="0" aria-label="Start building Bio-Data">
                <div class="icon icon-purple">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="boxhead"><p>BIO-DATA</p></div>
                <div class="boxtext"><p>Prepare a complete bio-data with personal, family, and educational details — ideal for jobs and matrimony.</p></div>
                <div class="footer"><p>Start building</p></div>
            </div>
        </div>
    </div>

    <script>
        function handleCardClick(type) {
            // Animate the card click
            const cardMap = { 'resume': 'card-resume', 'cover-letter': 'card-cover', 'bio-data': 'card-biodata' };
            const card = document.getElementById(cardMap[type]);
            if (card) {
                card.classList.add('card-pressed');
                setTimeout(() => card.classList.remove('card-pressed'), 200);
            }
            // Navigate to the builder page (create these pages later)
            const pageMap = { 'resume': 'resume.php', 'cover-letter': 'cover-letter.php', 'bio-data': 'bio-data.php' };
            setTimeout(() => {
                if (pageMap[type]) window.location.href = pageMap[type];
            }, 200);
        }

        // Also allow keyboard Enter/Space to trigger click
        document.querySelectorAll('.card-clickable').forEach(function(card) {
            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    </script>
</body>
</html>
