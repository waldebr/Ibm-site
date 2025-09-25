<?php
    session_start();
    require_once 'config.php';

    if (isset($_POST['register'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $checkEmail = $conn->query("SELECT email FROM users WHERE email = '$email'");
        if ($checkEmail->num_rows > 0) {
            $_SESSION['register_error'] = 'email já registrado.';
            $_SESSION['active_form'] = 'register';
        } else {
            $conn->query("INSERT INTO users  (name, email, password) VALUES ('$name', '$email', '$password')");
            // Corrigido: o valor da sessão deve ser 'register' (sem o ponto).
            $_SESSION['active_form'] = 'login';
        }

        header("Location: index.php");
        exit();
    }

if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $result = $conn->query("SELECT * FROM users WHERE email = '$email'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])) {
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            header("Location: user_page.php");
           exit();
        }
    }
    $_SESSION['login_error'] = 'email ou senha incorreta.';
    // Corrigido: o valor da sessão deve ser 'login' (sem o ponto).
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

?>