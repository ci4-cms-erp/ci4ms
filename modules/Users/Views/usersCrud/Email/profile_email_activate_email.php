<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Aktivasyonu</title>
    <style>
        /* Bu style bloğu sadece destekleyen istemciler için, asıl iş aşağıda inline style'larda */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        table {
            border-collapse: collapse;
        }

        .button:hover {
            background-color: #0056b3 !important;
        }
    </style>
</head>

<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">

                <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">

                    <tr>
                        <td align="center" style="background-color: #333333; padding: 20px; color: #ffffff; font-size: 24px; font-weight: bold;">
                            CI4MS
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px 30px; text-align: left; color: #333333; line-height: 1.6;">
                            <h2 style="margin-top: 0; color: #333333;">Merhaba <?php echo esc($user->username) ?>,</h2>

                            <p style="font-size: 16px; margin-bottom: 20px;">
                                Mail adresiniz tarafınızdan güncellenmiştir.
                                Ancak güvenliğiniz için sisteme giriş yapmadan önce hesabınızı doğrulamanız gerekmektedir.
                            </p>

                            <p style="font-size: 16px; margin-bottom: 30px;">
                                Hesabınızı aktif etmek ve giriş yapmak için aşağıdaki butona tıklayın:
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <a href="<?php echo $url ?>" target="_blank" style="display: inline-block; padding: 14px 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                                            Hesabımı Aktif Et
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin-top: 30px; font-size: 14px; color: #666;">
                                Eğer buton çalışmıyorsa, aşağıdaki linki tarayıcınıza kopyalayıp yapıştırabilirsiniz:
                            </p>

                            <p style="font-size: 12px; color: #007bff; word-break: break-all;">
                                <a href="<?php echo $url ?>" style="color: #007bff;"><?php echo $url ?></a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="background-color: #eeeeee; padding: 20px; font-size: 12px; color: #777777;">
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
