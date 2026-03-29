<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Aktivasyonu</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .button:hover { background-color: #123a49 !important; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f7f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(18, 58, 73, 0.1); border-top: 5px solid #2da592;">
                    <tr>
                        <td align="center" style="background-color: #123a49; padding: 30px 20px; color: #ffffff; font-size: 28px; font-weight: bold; letter-spacing: 1px;">
                            CI4MS
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px; text-align: left; color: #333333; line-height: 1.6; font-size: 16px;">
                            <h2 style="margin-top: 0; color: #123a49; font-size: 22px;">Merhaba <?php echo esc($user->username) ?>,</h2>

                            <p style="font-size: 16px; margin-bottom: 20px;">
                                Mail adresiniz tarafınızdan güncellenmiştir.
                                Ancak güvenliğiniz için sisteme giriş yapmadan önce hesabınızı doğrulamanız gerekmektedir.
                            </p>

                            <p style="font-size: 16px; margin-bottom: 30px;">
                                Hesabınızı aktif etmek ve giriş yapmak için aşağıdaki butona tıklayın:
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 10px 0 30px 0;">
                                        <a href="<?php echo $url ?>" target="_blank" class="button" style="display: inline-block; padding: 14px 30px; background-color: #2da592; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; transition: background-color 0.3s;">
                                            Hesabımı Aktif Et
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin-top: 20px; font-size: 14px; color: #666;">
                                Eğer buton çalışmıyorsa, aşağıdaki linki tarayıcınıza kopyalayıp yapıştırabilirsiniz:
                            </p>
                            <p style="font-size: 12px; color: #2da592; word-break: break-all;">
                                <a href="<?php echo $url ?>" style="color: #2da592;"><?php echo $url ?></a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color: #f9fbfb; padding: 20px; font-size: 13px; color: #666666; border-top: 1px solid #eeeeee;">
                            <p style="margin: 0;">&copy; <?php echo date('Y') ?> CI4MS Yönetim Paneli. Tüm hakları saklıdır.</p>
                            <p style="margin: 5px 0 0 0;">Bu mail otomatik olarak gönderilmiştir, lütfen cevaplamayınız.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
