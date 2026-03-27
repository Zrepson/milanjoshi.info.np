<?php
require 'pro-send-quote.php';

// Include Google Cloud dependencies using Composer
use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;

/**
  * Create an assessment to analyze the risk of a UI action.
  * @param string $recaptchaKey The reCAPTCHA key associated with the site/app
  * @param string $token The generated token obtained from the client.
  * @param string $project Your Google Cloud Project ID.
  * @param string $action Action name corresponding to the token.
  */
function create_assessment(
  string $recaptchaKey,
  string $token,
  string $project,
  string $action
): void {
  // Create the reCAPTCHA client.
  // TODO: Cache the client generation code (recommended) or call client.close() before exiting the method.
  $client = new RecaptchaEnterpriseServiceClient();
  $projectName = $client->projectName($project);

  // Set the properties of the event to be tracked.
  $event = (new Event())
    ->setSiteKey($recaptchaKey)
    ->setToken($token);

  // Build the assessment request.
  $assessment = (new Assessment())
    ->setEvent($event);

  $request = (new CreateAssessmentRequest())
  ->setParent($projectName)
  ->setAssessment($assessment);

  try {
    $response = $client->createAssessment($request);

    // Check if the token is valid.
    if ($response->getTokenProperties()->getValid() == false) {
      printf('The CreateAssessment() call failed because the token was invalid for the following reason: ');
      printf(InvalidReason::name($response->getTokenProperties()->getInvalidReason()));
      return;
    }

    // Check if the expected action was executed.
    if ($response->getTokenProperties()->getAction() == $action) {
      // Get the risk score and the reason(s).
      // For more information on interpreting the assessment, see:
      // https://cloud.google.com/recaptcha/docs/interpret-assessment
      printf('The score for the protection action is:');
      printf($response->getRiskAnalysis()->getScore());
    } else {
      printf('The action attribute in your reCAPTCHA tag does not match the action you are expecting to score');
    }
  } catch (exception $e) {
    printf('CreateAssessment() call failed with the following error: ');
    printf($e);
  }
}

// TODO: Replace the token and reCAPTCHA action variables before running the sample.
create_assessment(
   '6LfB75ksAAAAAJH1HtjfPi48ZBOBpHuoZ4XAfei0',
   'YOUR_USER_RESPONSE_TOKEN',
   'first-renderer-440905-b5',
   'YOUR_RECAPTCHA_ACTION'
);
?>
<?php
// CONFIGURATION
$to_email = "mail@milanjoshi.com.np"; // Your email
$whatsapp_number = "977XXXXXXXXX"; // Your WhatsApp number (with country code)
$csv_file = "leads.csv";

// MySQL Database configuration
$db_host = "localhost";
$db_user = "DB_USERNAME";
$db_pass = "DB_PASSWORD";
$db_name = "DB_NAME";
$db_table = "leads";

// 1️⃣ Check POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2️⃣ Verify reCAPTCHA
    $recaptcha_secret = "YOUR_SECRET_KEY";
    $recaptcha_response = $_POST['g-recaptcha-response'];
    $recaptcha_verify = file_get_contents(
        "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}"
    );
    $recaptcha_data = json_decode($recaptcha_verify);
    if (!$recaptcha_data->success) {
        die("reCAPTCHA verification failed. Please try again.");
    }

    // 3️⃣ Collect and sanitize form data
    $name = strip_tags(trim($_POST['name']));
    $phone = strip_tags(trim($_POST['phone']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $age = strip_tags(trim($_POST['age']));
    $plan = strip_tags(trim($_POST['plan']));
    $contact = strip_tags(trim($_POST['contact']));
    $message = strip_tags(trim($_POST['message']));

    // 4️⃣ Prepare email content
    $subject = "New Life Insurance Quote Request";
    $body = "New lead received:\n\nName: $name\nPhone: $phone\nEmail: $email\nAge Group: $age\nInsurance Plan: $plan\nPreferred Contact: $contact\nMessage: $message";

    $headers = "From: $name <$email>\r\nReply-To: $email\r\n";

    // 5️⃣ Send Email
    mail($to_email, $subject, $body, $headers);

    // 6️⃣ Save to CSV
    $csv_data = [$name, $phone, $email, $age, $plan, $contact, $message, date("Y-m-d H:i:s")];
    $file = fopen($csv_file, 'a');
    fputcsv($file, $csv_data);
    fclose($file);

    // 7️⃣ Save to MySQL
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);
    $stmt = $conn->prepare("INSERT INTO $db_table (name, phone, email, age, plan, contact, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $name, $phone, $email, $age, $plan, $contact, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // 8️⃣ WhatsApp notification via wa.me
    $wa_message = urlencode("New Lead:\nName: $name\nPhone: $phone\nEmail: $email\nPlan: $plan");
    $wa_link = "https://wa.me/$whatsapp_number?text=$wa_message";
    // Open link in new tab for admin (optional)
    // echo "<script>window.open('$wa_link');</script>";

    // 9️⃣ Redirect to thank-you page
    header("Location: thank-you.html");
    exit;

} else {
    echo "Invalid request.";
}
?>
