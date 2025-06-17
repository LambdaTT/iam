<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar Acesso</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f4f4f4;
    }

    table {
      border-spacing: 0;
    }

    td {
      padding: 0;
    }

    img {
      border: 0;
    }

    .wrapper {
      width: 100%;
      table-layout: fixed;
      background-color: #f4f4f4;
      padding: 30px 0;
    }

    .main {
      background-color: #ffffff;
      width: 100%;
      max-width: 600px;
      border-radius: 8px;
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    }

    .header {
      background-color: #0073e6;
      color: #ffffff;
      text-align: center;
      padding: 20px;
      font-family: Arial, sans-serif;
      font-size: 24px;
      font-weight: bold;
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
    }

    .content {
      padding: 20px;
      font-family: Arial, sans-serif;
      font-size: 16px;
      color: #333;
      text-align: left;
    }

    .button {
      text-align: center;
      padding: 20px;
    }

    .button a {
      background-color: #0073e6;
      color: #ffffff;
      text-decoration: none;
      padding: 12px 20px;
      border-radius: 5px;
      font-size: 16px;
      font-family: Arial, sans-serif;
      display: inline-block;
    }

    .footer {
      background-color: #f4f4f4;
      text-align: center;
      padding: 10px;
      font-size: 12px;
      color: #777;
      font-family: Arial, sans-serif;
    }

    @media screen and (max-width: 600px) {
      .main {
        width: 100%;
        border-radius: 0;
      }

      .header {
        border-radius: 0;
      }
    }

    #logo-container {
      padding: 15px;
      background-color: #fefefe;
      border-radius: 3px;
      box-shadow: 0px 0px 5px black inset;
      display: inline-block;
      width: 50%;
    }
  </style>
</head>

<body>
  <table class="wrapper" width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center">
        <table class="main" width="600" cellspacing="0" cellpadding="0">
          <!-- Header -->
          <tr>
            <td class="header">
              <div id="logo-container">
                <img style="width: 100%" src="https://sindiapp-media-archive.s3.us-east-1.amazonaws.com/logo.png">
              </div>
            </td>
          </tr>
          <!-- Content -->
          <tr>
            <td class="content">
              <p>Olá, <?php echo $user->fullName; ?></p>
              <p>Você está recebendo esta mensagem porquê foi solicitada a recuperação de acesso de sua conta no aplicativo de seu sindicato.</p>
              <p>Para recuperar seu acesso, basta clicar no botão abaixo.</p>
            </td>
          </tr>
          <!-- Button -->
          <tr>
            <td class="button">
              <a href="<?php echo $url; ?>">Recuperar Acesso</a>
            </td>
          </tr>
          <tr>
            <td class="content">
              <p>Se você não solicitou esta recuperação de acesso, basta ignorar esta mensagem seguramente.</p>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td class="footer">
              &copy; <?php echo date('Y'); ?> Sindi App. Todos os direitos reservados.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>

</html>