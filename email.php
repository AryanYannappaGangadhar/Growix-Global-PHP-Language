<?php
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: <' . SMTP_FROM . '>' . "\r\n";

    // Attempt to send email
    return @mail($to, $subject, $message, $headers);
}
?>
