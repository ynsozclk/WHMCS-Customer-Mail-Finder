<?php
// WHMCS başlangıç dosyası
require_once('init.php');

use WHMCS\Database\Capsule;

// Google reCAPTCHA secret key
$secretKey = '6Lf5YAkqAAAAAAV2m-JWDSuEC24Dsc5mVFwlhg4K';

// POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Formdan gelen verileri al
    $firstname = isset($_POST['firstname']) ? $_POST['firstname'] : '';
    $lastname = isset($_POST['lastname']) ? $_POST['lastname'] : '';
    $phone_number_input = isset($_POST['phone_number']) ? preg_replace('/\D/', '', $_POST['phone_number']) : '';
    $custom_field_value = isset($_POST['custom_field_value']) ? $_POST['custom_field_value'] : '';
    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

    // Boş alan kontrolü
    if (empty($firstname) || empty($lastname) || empty($phone_number_input) || empty($custom_field_value) || empty($recaptcha_response)) {
        die('Lütfen tüm alanları doldurun.');
    }

    // reCAPTCHA doğrulaması
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_data = [
        'secret' => $secretKey,
        'response' => $recaptcha_response
    ];

    $ch = curl_init($recaptcha_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($recaptcha_data));
    $verify = curl_exec($ch);
    curl_close($ch);

    $captcha_success = json_decode($verify);

    if (!$captcha_success->success) {
        echo 'reCAPTCHA doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
        var_dump($captcha_success);
        exit();
    }

    // WHMCS veritabanında müşteriyi bul
    $clients = Capsule::table('tblclients')
        ->where('firstname', $firstname)
        ->where('lastname', $lastname)
        ->get();

    $client_found = false;
    foreach ($clients as $client) {
        // Müşteri bulundu, şimdi telefon numarasını al ve eşleştirmeye çalış
        $phone_number_db = preg_replace('/\D/', '', $client->phonenumber); // Remove non-numeric characters from phone_number

        if (substr($phone_number_db, -4) == substr($phone_number_input, -4)) {
            // Telefon numaraları eşleşti, şimdi custom field değerini kontrol et
            $client_id = $client->id;

            // tblcustomfieldsvalues tablosunda relid ile bağlantı yaparak custom field değerini kontrol et
            $custom_field = Capsule::table('tblcustomfieldsvalues')
                ->where('relid', $client_id)
                ->where('fieldid', 5) // Örneğin customfield5'in fieldid'si
                ->where('value', $custom_field_value)
                ->first();

            if ($custom_field) {
                // Custom field değeri doğru, müşteri email adresini al ve yazdır
                $client_email = $client->email;
                $client_found = true;
                break;
            }
        }
    }

    if (!$client_found) {
        // Müşteri bulunamadı veya koşullar sağlanamadı
        echo "Müşteri bulunamadı veya gerekli koşullar sağlanamadı.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Email Adresini Öğren</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        .container {
            max-width: 400px;
            margin-top: 50px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .alert-success {
            font-size: 18px;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center">Email Adresini Öğren</h2>
        <form method="POST" action="mailcheck.php">
            <div class="form-group">
                <label for="firstname">İsim:</label>
                <input type="text" class="form-control" id="firstname" name="firstname" required>
            </div>

            <div class="form-group">
                <label for="lastname">Soyisim:</label>
                <input type="text" class="form-control" id="lastname" name="lastname" required>
            </div>

            <div class="form-group">
                <label for="phone_number">Telefon Numarası (Son 4 Hane):</label>
                <input type="text" class="form-control" id="phone_number" name="phone_number" required maxlength="4" oninput="validateNumericInput(this)">
            </div>

            <div class="form-group">
                <label for="custom_field_value">TC Kimlik Numarası (11 Rakam):</label>
                <input type="text" class="form-control" id="custom_field_value" name="custom_field_value" required maxlength="11" oninput="validateNumericInput(this)">
            </div>

            <!-- Google reCAPTCHA -->
            <div class="form-group">
                <div class="g-recaptcha" data-sitekey="6Lf5YAkqAAAAAJen3vBRcmLxwRDDAA6Jx4MTOgbj"></div>
            </div>

            <button type="submit" class="btn btn-primary">Bul</button>
        </form>

        <!-- Success message -->
        <?php if (isset($client_email)): ?>
            <div class="alert alert-success">
                Email adresiniz: <strong><?php echo $client_email; ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script>
        function validateNumericInput(input) {
            input.value = input.value.replace(/\D/g, '');
        }
    </script>
</body>
</html>
