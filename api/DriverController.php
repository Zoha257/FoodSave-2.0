<?php
require_once 'APIController.php';
require_once '../includes/classes/Driver.php';

class DriverController extends APIController {
    public function __construct() {
        parent::__construct();
        $this->authenticate();
    }

    private function requireDriver() {
        if (($this->user['user_type'] ?? '') !== 'driver') $this->sendError('Unauthorized', 403);
    }

    public function listDeliveryTasks() {
        $this->validateMethod('GET');
        $this->requireDriver();
        $status=$_GET['status'] ?? 'pending';
        try {
            $driver=new Driver($this->pdo);
            $tasks=$driver->listDeliveryTasks($status, (int)$this->user['id']);
            $this->sendResponse(['tasks'=>$tasks]);
        } catch (Exception $e) { $this->sendError('Server error',500); }
    }

    public function acceptTask() {
        $this->validateMethod('POST');
        $this->requireDriver();
        $data=$this->getRequestBody();
        if (empty($data['task_id'])) $this->sendError('Task ID is required',422);
        try {
            (new Driver($this->pdo))->acceptTask((int)$data['task_id'],(int)$this->user['id']);
            $this->sendResponse(['message'=>'Task accepted successfully']);
        } catch (Exception $e) { $this->sendError($e->getMessage(),409); }
    }

    public function updateTaskStatus($id) {
        $this->validateMethod('PUT');
        $this->requireDriver();
        $data=$this->getRequestBody();
        if (empty($data['status'])) $this->sendError('Status is required',422);
        $lat=($data['latitude'] ?? '') === '' ? null : (float)$data['latitude'];
        $lng=($data['longitude'] ?? '') === '' ? null : (float)$data['longitude'];
        $notes=isset($data['notes']) ? trim($data['notes']) : null;
        try {
            (new Driver($this->pdo))->updateTaskStatus((int)$id,$data['status'],(int)$this->user['id'],$lat,$lng,$notes);
            $this->sendResponse(['message'=>'Task status updated successfully']);
        } catch (Exception $e) {
            $code=$e->getMessage()==='Unauthorized'?403:409;
            $this->sendError($e->getMessage(),$code);
        }
    }
}
?>
