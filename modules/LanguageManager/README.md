# Language Manager Modülü

## Genel Bakış

Uygulama dillerini ve çeviri anahtarlarını backend panelinden yönetmenizi sağlar. Yeni dil ekleme, çeviri dosyası düzenleme ve eksik çevirileri tespit etme özellikleri sunar.

## Veritabanı Tabloları

- **languages**: Tanımlı diller (kod, isim, bayrak, aktif durumu, varsayılan dil)
- **language_translations**: Çeviri kayıtları (dil kodu, dosya, anahtar, değer)

## Temel Özellikler

### 1. Dil Yönetimi

`Backend → Dil Yönetimi → Diller`:

- Yeni dil ekleme (ör. `de` - Almanca)
- Aktif/pasif yapma
- Varsayılan dil seçimi

### 2. Çeviri Düzenleme

`Backend → Dil Yönetimi → Çeviriler`:

- Dosya bazlı çeviri listesi (Backend.php, Crm.php vb.)
- Satır satır çeviri düzenleme
- Eksik çevirileri otomatik tespit

### 3. Çeviri Senkronizasyonu

Varsayılan dildeki tüm anahtarlar, diğer dillere otomatik olarak senkronize edilir. Eksik anahtarlar "çevrilmemiş" olarak işaretlenir.

### 4. İçe/Dışa Aktarma

- PHP dil dosyalarından veritabanına aktarma
- Veritabanından PHP dosyasına geri yazma

## Rota Yapısı

| Rota                                           | Açıklama      |
| ---------------------------------------------- | ------------- |
| `backend/language-manager`                     | Dil listesi   |
| `backend/language-manager/create`              | Yeni dil      |
| `backend/language-manager/update/{id}`         | Dil düzenle   |
| `backend/language-manager/delete/{id}`         | Dil sil       |
| `backend/language-manager/toggle/{id}`         | Aktif/pasif   |
| `backend/language-manager/translations/{code}` | Çeviriler     |
| `backend/language-manager/translations/save`   | Çeviri kaydet |
| `backend/language-manager/sync/{code}`         | Senkronize et |
