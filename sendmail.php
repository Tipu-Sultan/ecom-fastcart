<?php
include ('themancode.php');
include ('smtp/PHPMailerAutoload.php');

function sendmailTO($email, $html, $subject)
{
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = "smtp.gmail.com";
  $mail->Port = 587;
  $mail->SMTPSecure = "tls";
  $mail->SMTPAuth = true;
  $mail->Username = "themancode7@gmail.com";
  $mail->Password = "kofypkejuqvoyujy";
  $mail->SetFrom("themancode7@gmail.com");
  $mail->addAddress("$email");
  $mail->IsHTML(true);
  $mail->Subject = $subject;
  $mail->Body = $html;
  $mail->SMTPOptions = array(
    'ssl' => array(
      'verify_peer' => false,
      'verify_peer_name' => false,
      'allow_self_signed' => false
    )
  );

  if ($mail->send()) {
    return true;
  }
}

