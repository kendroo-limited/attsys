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
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS designations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            department_id INT NOT NULL,
            name VARCHAR(128) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_desig_dept (tenant_id, department_id, name),
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
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
        $departments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $desigStmt = $pdo->prepare("SELECT * FROM designations WHERE tenant_id = ? ORDER BY name ASC");
        $desigStmt->execute([$tenantId]);
        $allDesignations = $desigStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group designations by department
        $designationsByDept = [];
        foreach ($allDesignations as $d) {
            $designationsByDept[$d['department_id']][] = $d;
        }

        foreach ($departments as &$dept) {
            $dept['designations'] = $designationsByDept[$dept['id']] ?? [];
        }

        echo json_encode(['departments' => $departments]);
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

    public function createDesignation()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $departmentId = $data['department_id'] ?? null;

        if (!$name || !$departmentId) {
            http_response_code(400);
            echo json_encode(['error' => 'Designation name and department_id are required']);
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
            $stmt = $pdo->prepare("INSERT INTO designations (tenant_id, department_id, name) VALUES (?, ?, ?)");
            $stmt->execute([$tenantId, $departmentId, $name]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'name' => $name, 'message' => 'Designation created']);
        } catch (\PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                http_response_code(400);
                echo json_encode(['error' => 'Designation already exists in this department']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
    }

    public function updateDesignation()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $id = $_GET['id'] ?? null;

        if (!$id || !$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Designation ID and name are required']);
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
            $pdo->beginTransaction();

            $oldStmt = $pdo->prepare("SELECT d.name, dept.name as dept_name FROM designations d JOIN departments dept ON d.department_id = dept.id WHERE d.id = ? AND d.tenant_id = ? FOR UPDATE");
            $oldStmt->execute([$id, $tenantId]);
            $row = $oldStmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && $row['name'] !== $name) {
                $stmt = $pdo->prepare("UPDATE designations SET name = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$name, $id, $tenantId]);

                // Update employees
                $empStmt = $pdo->prepare("UPDATE employees SET designation = ? WHERE designation = ? AND department = ? AND tenant_id = ?");
                $empStmt->execute([$name, $row['name'], $row['dept_name'], $tenantId]);
            }
            $pdo->commit();
            echo json_encode(['message' => 'Designation updated']);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                http_response_code(400);
                echo json_encode(['error' => 'Designation already exists in this department']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
    }

    public function deleteDesignation()
    {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Designation ID is required']);
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
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE tenant_id = ? AND designation = (SELECT name FROM designations WHERE id = ? AND tenant_id = ?) AND department = (SELECT dept.name FROM designations d JOIN departments dept ON d.department_id = dept.id WHERE d.id = ? AND d.tenant_id = ?)");
        $checkStmt->execute([$tenantId, $id, $tenantId, $id, $tenantId]);
        if ($checkStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete designation: it is assigned to employees.']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM designations WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        echo json_encode(['message' => 'Designation deleted']);
    }
}
