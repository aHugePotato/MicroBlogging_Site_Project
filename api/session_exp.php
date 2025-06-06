<?php

if (isset($_SESSION['loginTime']) && $_SESSION['loginTime'] - time() >= 604800)
    session_unset();
