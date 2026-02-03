<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Údržba | Kubaja Designs</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --kjd-dark-brown: #3e2723;
            --kjd-gold-brown: #8d6e63;
            --kjd-beige: #d7ccc8;
            --kjd-light-beige: #efebe9;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--kjd-light-beige);
            color: var(--kjd-dark-brown);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
        }
        
        .maintenance-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(62, 39, 35, 0.1);
            max-width: 600px;
            width: 90%;
        }
        
        .icon-wrapper {
            font-size: 4rem;
            color: var(--kjd-gold-brown);
            margin-bottom: 1.5rem;
        }
        
        h1 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--kjd-dark-brown);
        }
        
        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #666;
            margin-bottom: 2rem;
        }
        
        .loader {
            width: 48px;
            height: 48px;
            border: 5px solid var(--kjd-beige);
            border-bottom-color: var(--kjd-gold-brown);
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }
        
        @keyframes rotation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .admin-link {
            display: inline-block;
            margin-top: 2rem;
            color: var(--kjd-gold-brown);
            text-decoration: none;
            font-size: 0.9rem;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .admin-link:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="icon-wrapper">
            <i class="fas fa-tools"></i>
        </div>
        <h1>Právě vylepšujeme náš web</h1>
        <p>
            Omlouváme se, ale naše stránky jsou momentálně v režimu údržby. 
            Pracujeme na tom, aby byl váš zážitek z nakupování ještě lepší. 
            <br><br>
            Prosím, zkuste to znovu za pár hodin.
        </p>
        <div class="loader"></div>
        
        <div>
            <a href="admin/admin_login.php" class="admin-link"><i class="fas fa-lock me-1"></i> Admin přístup</a>
        </div>
    </div>
</body>
</html>
