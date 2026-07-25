<?php

return [
    'notifications'    => 'Bildirimler',
    'title'            => 'Bildirim Merkezi',
    'unread'           => 'okunmamış',
    'markAllRead'      => 'Tümünü okundu işaretle',
    'markRead'         => 'Okundu işaretle',
    'noNotifications'  => 'Henüz bildirim yok.',
    'viewAll'          => 'Tümünü gör',
    'severityInfo'     => 'Bilgi',
    'severityWarning'  => 'Uyarı',
    'severityCritical' => 'Kritik',

    // SSE stream() 429 gövdesi — kısa ve ayrıntısız tutulur.
    'realtimeConnLimit' => 'Çok fazla açık anlık bağlantı var.',

    // Tercih (opt-out) ekranı.
    'preferences'      => 'Bildirim tercihleri',
    'prefIntro'        => 'Almak istemediğin bildirim tiplerini sustur. Susturma yalnız kendi hesabını etkiler.',
    'prefType'         => 'Bildirim tipi',
    'prefChannelAll'   => 'Tüm kanallar',
    'prefMuted'        => 'Susturuldu',
    'prefSave'         => 'Tercihleri kaydet',
    'prefSaved'        => 'Bildirim tercihleri güncellendi.',
    'prefCriticalNote' => 'Kritik bildirimler her zaman iletilir, susturulamaz.',
    'prefNoTypes'      => 'Henüz susturulabilir bir bildirim tipi kayıtlı değil.',
    'prefTableMissing' => 'Bildirim tercihleri tablosu henüz migrate edilmemiş.',
    'prefTypeAudit'    => 'Güvenlik ve denetim uyarıları',

    // Bildirim gönderme (composer) ekranı.
    'compose'                 => 'Bildirim gönder',
    'composeIntro'            => 'Bildirimi yaz ve kimlere gideceğini seç. Gönderen olarak daima kendi hesabın kaydedilir.',
    'composeFieldTitle'       => 'Başlık',
    'composeFieldBody'        => 'Mesaj',
    'composeFieldUrl'         => 'Bağlantı',
    'composeFieldSeverity'    => 'Önem',
    'composeFieldMode'        => 'Hedef kitle',
    'composeFieldUsers'       => 'Kullanıcılar',
    'composeFieldGroups'      => 'Gruplar',
    'composeFieldExclude'     => 'Hariç tutulanlar',
    'composeUrlHelp'          => 'İsteğe bağlı. / ile başlayan site-içi bir yol ya da tam http(s) adresi olmalı.',
    'composeExcludeHelp'      => 'Bu kullanıcılar hedefe uysalar bile bildirimi almaz.',
    'composeModeBroadcast'    => 'Herkes',
    'composeModeTargeted'     => 'Seçili kullanıcılar ve gruplar',
    'composeSelectUsers'      => 'Kullanıcı ara…',
    'composeSelectGroups'     => 'Grup seç…',
    'composeNoGroups'         => 'Henüz tanımlı grup yok.',
    'composePreview'          => 'Alıcıları önizle',
    'composeRecipients'       => 'Tahmini alıcı sayısı: {0}',
    'composeRecipientsFailed' => 'Alıcı önizlemesi alınamadı.',
    'composeSubmit'           => 'Bildirimi gönder',
    'composeSent'             => 'Bildirim {0} alıcıya gönderildi.',
    'composeFailed'           => 'Bildirim iletilemedi.',
    'composePartial'          => 'Bildirimin {1} satırından yalnız {0} tanesi yazılabildi; hedef kitlenin bir bölümüne ulaşılmadı. Yeniden göndermeden önce logu kontrol et.',
    'composeNoTarget'         => 'En az bir kullanıcı ya da grup seç, ya da herkese gönder.',
    'composeUnknownGroup'     => 'Tanınmayan bir grup seçildi.',
    'composeUnknownUser'      => 'Tanınmayan bir kullanıcı seçildi.',
    'composeTooManyTargets'   => 'Tek bir bildirimde en fazla {0} kullanıcı ve grup seçilebilir.',
    // Literal süslü parantezler ICU'da quote'lanmıştır ('{' / '}'): doğrulayıcı bu satırı
    // MessageFormatter'dan geçirir, quote'lanmamış bir parantez tüm kalıbın parse
    // edilmesini bozar ve yöneticiye alan adı yerine ham {field} yer tutucusu görünür.
    'composeInvalidText'      => '{field} alanı < > \'{\' \'}\' = karakterlerini içeremez.',
    'composeInvalidUrl'       => 'Bağlantı / ile başlayan site-içi bir yol olmalı; buradan dış adres gönderilemez.',
    'composeTitleRequired'    => 'Başlık zorunlu.',
    'composeInvalidSeverity'  => 'Geçerli bir önem seviyesi seç.',
    'composeInvalidMode'      => 'Geçerli bir hedef kitle seç.',
];
