<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

class Email {
    private $mailer;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->mailer = new PHPMailer(true);
        
        // Configure PHPMailer
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['SMTP_HOST'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['SMTP_USERNAME'];
            $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $_ENV['SMTP_PORT'];
            $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        } catch (Exception $e) {
            error_log("Error configuring mailer: " . $e->getMessage());
        }
    }
    
    /**
     * Send email using template
     */
    public function sendEmail($to, $subject, $template, $data) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            
            // Get template content
            $templateContent = $this->getEmailTemplate($template);
            
            // Replace placeholders with data
            foreach ($data as $key => $value) {
                $templateContent = str_replace("{{" . $key . "}}", $value, $templateContent);
            }
            
            $this->mailer->Body = $templateContent;
            $this->mailer->AltBody = strip_tags($templateContent);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template content
     */
    private function getEmailTemplate($template) {
        $templatePath = __DIR__ . "/../email_templates/{$template}.html";
        if (file_exists($templatePath)) {
            return file_get_contents($templatePath);
        }
        throw new Exception("Email template not found: {$template}");
    }
    
    /**
     * Send donation expiry notification
     */
    public function sendExpiryNotification($donationId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT fd.*, u.email, u.full_name 
                FROM food_donations fd 
                JOIN users u ON fd.donor_id = u.id 
                WHERE fd.id = ?
            ");
            $stmt->execute([$donationId]);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($donation) {
                return $this->sendEmail(
                    $donation['email'],
                    'Donation Expiring Soon',
                    'donation_expiry',
                    [
                        'name' => $donation['full_name'],
                        'title' => $donation['title'],
                        'expiry_date' => date('F j, Y', strtotime($donation['expiration_date'])),
                        'donation_link' => $_ENV['APP_URL'] . "/donor/view_donation.php?id=" . $donationId
                    ]
                );
            }
            return false;
        } catch (Exception $e) {
            error_log("Error sending expiry notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send pickup request notification
     */
    public function sendPickupRequestNotification($requestId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT pr.*, fd.title, u.email, u.full_name 
                FROM pickup_requests pr 
                JOIN food_donations fd ON pr.donation_id = fd.id 
                JOIN users u ON fd.donor_id = u.id 
                WHERE pr.id = ?
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                return $this->sendEmail(
                    $request['email'],
                    'New Pickup Request',
                    'pickup_request',
                    [
                        'name' => $request['full_name'],
                        'donation_title' => $request['title'],
                        'request_link' => $_ENV['APP_URL'] . "/donor/view_request.php?id=" . $requestId
                    ]
                );
            }
            return false;
        } catch (Exception $e) {
            error_log("Error sending pickup request notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send request status update notification
     */
    public function sendRequestStatusNotification($requestId, $status) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT pr.*, fd.title, u.email, u.full_name 
                FROM pickup_requests pr 
                JOIN users u ON pr.ngo_id = u.id 
                JOIN food_donations fd ON pr.donation_id = fd.id 
                WHERE pr.id = ?
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                return $this->sendEmail(
                    $request['email'],
                    'Pickup Request ' . ucfirst($status),
                    'request_status',
                    [
                        'name' => $request['full_name'],
                        'donation_title' => $request['title'],
                        'status' => ucfirst($status),
                        'request_link' => $_ENV['APP_URL'] . "/ngo/view_request.php?id=" . $requestId
                    ]
                );
            }
            return false;
        } catch (Exception $e) {
            error_log("Error sending status notification: " . $e->getMessage());
            return false;
        }
    }
}