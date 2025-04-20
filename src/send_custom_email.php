<?php include 'top.php'; ?>
<main class="form-area">
    <h2 class="section-title">Send Custom Email</h2>
    <div class="form-panel">
        <form method="POST">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" required>

            <label for="body">Message</label>
            <textarea id="body" name="body" rows="6" required></textarea>

            <button type="submit">Send Email</button>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>