<?php
/**
 * Generate and persist FoodSave expiry-risk decision support for a donation.
 * Returns false when the optional Python service is unavailable.
 */
function generateDonationExpiryPrediction(PDO $pdo, $donationId, array $features)
{
    $pythonExecutable = resolveFoodSavePythonExecutable();
    $pythonScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'predict_api.py';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $command = escapeshellarg($pythonExecutable) . ' ' . escapeshellarg($pythonScript);
    $process = @proc_open($command, $descriptors, $pipes, dirname(__DIR__));

    if (!is_resource($process)) {
        error_log('FoodSave AI process could not be started.');
        return false;
    }

    fwrite($pipes[0], json_encode($features));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $returnCode = proc_close($process);

    $prediction = json_decode(trim($output), true);
    if (
        $returnCode !== 0 ||
        !is_array($prediction) ||
        isset($prediction['error']) ||
        !isset($prediction['risk_probability'], $prediction['risk_level'])
    ) {
        error_log('FoodSave AI prediction failed: ' . trim($error ?: $output));
        return false;
    }

    $modelVersion = $prediction['model_version'] ?? 'logistic-v1';
    $riskLevel = strtoupper($prediction['risk_level']);

    try {
        $update = $pdo->prepare('
            UPDATE food_donations
            SET ai_risk_probability = ?, ai_risk_level = ?,
                ai_predicted_at = NOW(), ai_model_version = ?
            WHERE id = ?
        ');
        $update->execute([
            $prediction['risk_probability'],
            $riskLevel,
            $modelVersion,
            $donationId
        ]);

        $log = $pdo->prepare('
            INSERT INTO ai_prediction_logs
                (donation_id, model_version, risk_probability, risk_level, feature_snapshot)
            VALUES (?, ?, ?, ?, ?)
        ');
        $log->execute([
            $donationId,
            $modelVersion,
            $prediction['risk_probability'],
            $riskLevel,
            json_encode($prediction['features'] ?? $features)
        ]);
    } catch (PDOException $exception) {
        error_log('FoodSave AI database persistence failed: ' . $exception->getMessage());
        return false;
    }

    return $prediction;
}

function resolveFoodSavePythonExecutable()
{
    $configured = trim((string) getenv('FOODSAVE_PYTHON'));
    if ($configured !== '' && is_file($configured)) {
        return $configured;
    }

    $candidates = [
        'C:\\xampp\\php\\python.exe',
        'C:\\Python313\\python.exe',
        'C:\\Users\\Laptopify\\AppData\\Local\\Programs\\Python\\Python313\\python.exe'
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return $configured !== '' ? $configured : 'python';
}
