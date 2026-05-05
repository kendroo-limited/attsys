<?php

namespace App\Controller;

use App\Core\Auth;
use App\Core\Database;

class DepartmentController
{
    private function getPdo(): ?\PDO
    {
        return Database::get();
    }

    private function getTenantId()
    {
        $user = Auth::currentUser();
        return $user['tenant_id'] ?? null;
    }

    private function ensureDepartmentsTable(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            name VARCHAR(128) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_dept_tenant (tenant_id, name)
        )");
    }

    public function list()
    {
        header('Content-Type: application/json');
        $pdo = $this->getPdo();
        if (!$pdo) {
            echo json_encode(['departments' => []]);
            return;
        }

        $this->ensureDepartmentsTable($pdo);
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            echo json_encode(['departments' => []]);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM departments WHERE tenant_id = ? ORDER BY name ASC");
        $stmt->execute([$tenantId]);
        echo json_encode(['departments' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function create()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Department name is required']);
            return;
        }

        $pdo = $this->getPdo();
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection required']);
            return;
        }

        $this->ensureDepartmentsTable($pdo);
        $tenantId = $this->getTenantId();

        try {
            $stmt = $pdo->prepare("INSERT INTO departments (tenant_id, name) VALUES (?, ?)");
            $stmt->execute([$tenantId, $name]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'name' => $name, 'message' => 'Department created']);
        } catch (\PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                http_response_code(400);
                echo json_encode(['error' => 'Department already exists']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
    }

    public function update()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $id = $_GET['id'] ?? null;

        if (!$id || !$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Department ID and name are required']);
            return;
        }

        $pdo = $this->getPdo();
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection required']);
            return;
        }

        $tenantId = $this->getTenantId();
        
        try {
            // To update safely (update the employee references as well)
            $pdo->beginTransaction();
            
            $oldNameStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $oldNameStmt->execute([$id, $tenantId]);
            $oldName = $oldNameStmt->fetchColumn();
            
            if ($oldName && $oldName !== $name) {
                $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$name, $id, $tenantId]);
                
                // Update employees
                $empStmt = $pdo->prepare("UPDATE employees SET department = ? WHERE department = ? AND tenant_id = ?");
                $empStmt->execute([$name, $oldName, $tenantId]);
            }
            $pdo->commit();
            echo json_encode(['message' => 'Department updated']);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                http_response_code(400);
                echo json_encode(['error' => 'Department already exists']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
    }

    public function delete()
    {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Department ID is required']);
            return;
        }

        $pdo = $this->getPdo();
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection required']);
            return;
        }

        $tenantId = $this->getTenantId();

        // Check if assigned to any employees
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE tenant_id = ? AND department = (SELECT name FROM departments WHERE id = ? AND tenant_id = ?)");
        $checkStmt->execute([$tenantId, $id, $tenantId]);
        if ($checkStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete department: it is assigned to employees.']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        echo json_encode(['message' => 'Department deleted']);
    }
}
