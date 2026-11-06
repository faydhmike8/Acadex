<?php
require "auth.php";

// Esvazia os dados da sessão e destrói-a
$_SESSION = [];
session_destroy();

redirecionar("login.php");
