<?php 
session_start();
require_once('DBConnection.php');

class Actions extends DBConnection {
    function __construct(){
        parent::__construct();
    }

    function __destruct(){
        parent::__destruct();
    }

    function login(){
        $username = $_POST['username'];
        $password = $_POST['password'];
        $sql = "SELECT * FROM admin_list WHERE username = ? AND password = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $username, md5($password));
        $stmt->execute();
        $qry = $stmt->get_result()->fetch_assoc();

        if(!$qry){
            $resp['status'] = "failed";
            $resp['msg'] = "Invalid username or password.";
        } else {
            $resp['status'] = "success";
            $resp['msg'] = "Login successfully.";
            foreach($qry as $k => $v){
                if(!is_numeric($k)) {
                    $_SESSION[$k] = $v;
                }
            }
        }
        return json_encode($resp);
    }

    function logout(){
        session_destroy();
        header("Location: ./admin");
        exit();
    }

    function save_admin(){
        $id = $_POST['id'] ?? '';
        $username = $_POST['username'];
        $data = [];
        $cols = [];
        $values = [];
        foreach($_POST as $k => $v){
            if($k != 'id'){
                if(!empty($id)){
                    $data[] = "`$k` = ?";
                } else {
                    $cols[] = $k;
                    $values[] = '?';
                }
            }
        }
        if(empty($id)){
            $cols[] = 'password';
            $values[] = md5($username);
        }
        $sql = empty($id) ? 
            "INSERT INTO `admin_list` (".implode(',', $cols).") VALUES (".implode(',', $values).")" :
            "UPDATE `admin_list` SET ".implode(',', $data)." WHERE admin_id = ?";

        $stmt = $this->db->prepare($sql);
        $params = array_merge(array_values($_POST), [md5($username)]);
        if(!empty($id)) $params[] = $id;
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $resp['msg'] = empty($id) ? 'New User successfully saved.' : 'User Details successfully updated.';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Saving User Details Failed. Error: '.$this->db->error;
            $resp['sql'] = $sql;
        }
        return json_encode($resp);
    }

    function delete_admin(){
        $id = $_POST['id'];
        $sql = "DELETE FROM `admin_list` WHERE admin_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'User successfully deleted.';
        } else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->db->error;
        }
        return json_encode($resp);
    }

    function update_credentials(){
        $id = $_SESSION['admin_id'];
        $old_password = $_POST['old_password'];
        $password = $_POST['password'];
        $data = [];
        foreach($_POST as $k => $v){
            if($k != 'id' && $k != 'old_password' && !empty($v)){
                if($k == 'password') $v = md5($v);
                $data[] = "`$k` = ?";
            }
        }
        $sql = "UPDATE `admin_list` SET ".implode(',', $data)." WHERE admin_id = ?";
        $stmt = $this->db->prepare($sql);

        if(!empty($password) && md5($old_password) != $_SESSION['password']){
            $resp['status'] = 'failed';
            $resp['msg'] = "Old password is incorrect.";
        } else {
            $params = array_merge(array_values($_POST), [$id]);
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            if($stmt->execute()){
                $resp['status'] = 'success';
                $_SESSION['flashdata']['type'] = 'success';
                $_SESSION['flashdata']['msg'] = 'Credential successfully updated.';
                foreach($_POST as $k => $v){
                    if($k != 'id' && $k != 'old_password' && !empty($v)){
                        if($k == 'password') $v = md5($v);
                        $_SESSION[$k] = $v;
                    }
                }
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Updating Credentials Failed. Error: '.$this->db->error;
                $resp['sql'] = $sql;
            }
        }
        return json_encode($resp);
    }

    function save_settings(){
        $about = $_POST['about'];
        $update = file_put_contents('./about.html', htmlentities($about));
        if($update){
            $resp['status'] = "success";
            $resp['msg'] = "Settings successfully updated.";
        } else {
            $resp['status'] = "failed";
            $resp['msg'] = "Failed to update settings.";
        }
        return json_encode($resp);
    }

    function save_department(){
        $id = $_POST['id'] ?? '';
        $data = [];
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if($k != 'id'){
                $v = trim($v);
                $v = $this->db->real_escape_string($v);
                if(empty($id)){
                    $cols[] = "`$k`";
                    $vals[] = '?';
                } else {
                    $data[] = "`$k` = ?";
                }
            }
        }
        if(empty($id)){
            $cols_join = implode(",", $cols);
            $vals_join = implode(",", $vals);
            $sql = "INSERT INTO `department_list` ($cols_join) VALUES ($vals_join)";
        } else {
            $sql = "UPDATE `department_list` SET ".implode(",", $data)." WHERE department_id = ?";
        }

        $stmt = $this->db->prepare($sql);
        $params = array_values($_POST);
        if(!empty($id)) $params[] = $id;
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);

        $check = $this->db->query("SELECT COUNT(department_id) AS `count` FROM `department_list` WHERE `name` = '{$name}' ".($id > 0 ? " AND department_id != '{$id}'" : ""))->fetch_array()['count'];
        if($check > 0){
            $resp['status'] = "failed";
            $resp['msg'] = "Department name already exists.";
        } else {
            if($stmt->execute()){
                $resp['status'] = "success";
                $resp['msg'] = empty($id) ? "Department successfully saved." : "Department successfully updated.";
            } else {
                $resp['status'] = "failed";
                $resp['msg'] = empty($id) ? "Saving New Department Failed." : "Updating Department Failed.";
                $resp['error'] = $this->db->error;
            }
        }

        return json_encode($resp);
    }

    function delete_department(){
        $id = $_POST['id'];
        $sql = "DELETE FROM `department_list` WHERE department_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Department successfully deleted.';
        } else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->db->error;
        }
        return json_encode($resp);
    }

    function update_stat_dept(){
        $id = $_POST['id'];
        $status = $_POST['status'];
        $sql = "UPDATE department_list SET status = ? WHERE department_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $status, $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $resp['msg'] = 'Department\'s status successfully updated';
            $_SESSION['flashdata']['type'] = $resp['status'];
            $_SESSION['flashdata']['msg'] = $resp['msg'];
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Department\'s status has failed to update.';
        }

        return json_encode($resp);
    }

    function save_designation(){
        $id = $_POST['id'] ?? '';
        $data = [];
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if($k != 'id'){
                $v = trim($v);
                $v = $this->db->real_escape_string($v);
                if(empty($id)){
                    $cols[] = "`$k`";
                    $vals[] = '?';
                } else {
                    $data[] = "`$k` = ?";
                }
            }
        }
        if(empty($id)){
            $cols_join = implode(",", $cols);
            $vals_join = implode(",", $vals);
            $sql = "INSERT INTO `designation_list` ($cols_join) VALUES ($vals_join)";
        } else {
            $sql = "UPDATE `designation_list` SET ".implode(",", $data)." WHERE designation_id = ?";
        }

        $stmt = $this->db->prepare($sql);
        $params = array_values($_POST);
        if(!empty($id)) $params[] = $id;
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);

        $check = $this->db->query("SELECT COUNT(designation_id) AS `count` FROM `designation_list` WHERE `name` = '{$name}' ".($id > 0 ? " AND designation_id != '{$id}'" : ""))->fetch_array()['count'];
        if($check > 0){
            $resp['status'] = "failed";
            $resp['msg'] = "Designation name already exists.";
        } else {
            if($stmt->execute()){
                $resp['status'] = "success";
                $resp['msg'] = empty($id) ? "Designation successfully saved." : "Designation successfully updated.";
            } else {
                $resp['status'] = "failed";
                $resp['msg'] = empty($id) ? "Saving New Designation Failed." : "Updating Designation Failed.";
                $resp['error'] = $this->db->error;
            }
        }

        return json_encode($resp);
    }

    function delete_designation(){
        $id = $_POST['id'];
        $sql = "DELETE FROM `designation_list` WHERE designation_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Designation successfully deleted.';
        } else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->db->error;
        }
        return json_encode($resp);
    }

    function update_stat_designation(){
        $id = $_POST['id'];
        $status = $_POST['status'];
        $sql = "UPDATE designation_list SET status = ? WHERE designation_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $status, $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $resp['msg'] = 'Designation\'s status successfully updated';
            $_SESSION['flashdata']['type'] = $resp['status'];
            $_SESSION['flashdata']['msg'] = $resp['msg'];
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Designation\'s status has failed to update.';
        }

        return json_encode($resp);
    }

    function save_employee(){
        $id = $_POST['id'] ?? '';
        $data = [];
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if($k != 'id'){
                $v = trim($v);
                $v = $this->db->real_escape_string($v);
                if(empty($id)){
                    $cols[] = "`$k`";
                    $vals[] = '?';
                } else {
                    $data[] = "`$k` = ?";
                }
            }
        }
        if(empty($id)){
            $cols_join = implode(",", $cols);
            $vals_join = implode(",", $vals);
            $sql = "INSERT INTO `employee_list` ($cols_join) VALUES ($vals_join)";
        } else {
            $sql = "UPDATE `employee_list` SET ".implode(",", $data)." WHERE employee_id = ?";
        }

        $stmt = $this->db->prepare($sql);
        $params = array_values($_POST);
        if(!empty($id)) $params[] = $id;
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);

        $check = $this->db->query("SELECT COUNT(employee_id) AS `count` FROM `employee_list` WHERE `name` = '{$name}' ".($id > 0 ? " AND employee_id != '{$id}'" : ""))->fetch_array()['count'];
        if($check > 0){
            $resp['status'] = "failed";
            $resp['msg'] = "Employee name already exists.";
        } else {
            if($stmt->execute()){
                $resp['status'] = "success";
                $resp['msg'] = empty($id) ? "Employee successfully saved." : "Employee successfully updated.";
            } else {
                $resp['status'] = "failed";
                $resp['msg'] = empty($id) ? "Saving New Employee Failed." : "Updating Employee Failed.";
                $resp['error'] = $this->db->error;
            }
        }

        return json_encode($resp);
    }

    function delete_employee(){
        $id = $_POST['id'];
        $sql = "DELETE FROM `employee_list` WHERE employee_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Employee successfully deleted.';
        } else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->db->error;
        }
        return json_encode($resp);
    }

    function update_stat_employee(){
        $id = $_POST['id'];
        $status = $_POST['status'];
        $sql = "UPDATE employee_list SET status = ? WHERE employee_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $status, $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $resp['msg'] = 'Employee\'s status successfully updated';
            $_SESSION['flashdata']['type'] = $resp['status'];
            $_SESSION['flashdata']['msg'] = $resp['msg'];
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Employee\'s status has failed to update.';
        }

        return json_encode($resp);
    }

    function save_payroll(){
        $id = $_POST['id'] ?? '';
        $data = [];
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if($k != 'id'){
                $v = trim($v);
                $v = $this->db->real_escape_string($v);
                if(empty($id)){
                    $cols[] = "`$k`";
                    $vals[] = '?';
                } else {
                    $data[] = "`$k` = ?";
                }
            }
        }
        if(empty($id)){
            $cols_join = implode(",", $cols);
            $vals_join = implode(",", $vals);
            $sql = "INSERT INTO `payroll_list` ($cols_join) VALUES ($vals_join)";
        } else {
            $sql = "UPDATE `payroll_list` SET ".implode(",", $data)." WHERE payroll_id = ?";
        }

        $stmt = $this->db->prepare($sql);
        $params = array_values($_POST);
        if(!empty($id)) $params[] = $id;
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);

        $check = $this->db->query("SELECT COUNT(payroll_id) AS `count` FROM `payroll_list` WHERE `name` = '{$name}' ".($id > 0 ? " AND payroll_id != '{$id}'" : ""))->fetch_array()['count'];
        if($check > 0){
            $resp['status'] = "failed";
            $resp['msg'] = "Payroll entry already exists.";
        } else {
            if($stmt->execute()){
                $resp['status'] = "success";
                $resp['msg'] = empty($id) ? "Payroll entry successfully saved." : "Payroll entry successfully updated.";
            } else {
                $resp['status'] = "failed";
                $resp['msg'] = empty($id) ? "Saving New Payroll Entry Failed." : "Updating Payroll Entry Failed.";
                $resp['error'] = $this->db->error;
            }
        }

        return json_encode($resp);
    }

    function delete_payroll(){
        $id = $_POST['id'];
        $sql = "DELETE FROM `payroll_list` WHERE payroll_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Payroll entry successfully deleted.';
        } else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->db->error;
        }
        return json_encode($resp);
    }

    function update_stat_payroll(){
        $id = $_POST['id'];
        $status = $_POST['status'];
        $sql = "UPDATE payroll_list SET status = ? WHERE payroll_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $status, $id);

        if($stmt->execute()){
            $resp['status'] = 'success';
            $resp['msg'] = 'Payroll entry\'s status successfully updated';
            $_SESSION['flashdata']['type'] = $resp['status'];
            $_SESSION['flashdata']['msg'] = $resp['msg'];
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Payroll entry\'s status has failed to update.';
        }

        return json_encode($resp);
    }
}
?>
