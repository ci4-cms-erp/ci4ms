# Dashboard Widgets Modülü

## Genel Bakış

Backend ana panelina özelleştirilebilir widget'lar ekleyerek istatistik, grafik ve hızlı bilgi kartları oluşturmanızı sağlar.

## Veritabanı Tabloları

- **dashboard_widgets**: Widget tanımları (tip, başlık, veri kaynağı, boyut, sıralama, renk)

## Temel Özellikler

### 1. Widget Tipleri

- **counter**: Tek sayısal değer kartı (toplam kullanıcı, yeni sipariş vb.)
- **chart**: Grafik widget (Chart.js ile bar/line/pie)
- **table**: Mini tablo widget'ı
- **html**: Serbest HTML içerik

### 2. Widget Oluşturma

`Backend → Dashboard Widgets → Yeni Widget`:

- **Başlık**: Widget başlığı
- **Tip**: counter / chart / table / html
- **Veri SQL'i**: Widget'ın göstereceği verileri çeken SQL sorgusu
- **Boyut**: col-3, col-4, col-6, col-12 (grid genişliği)
- **Sıralama**: Paneldeki gösterim sırası
- **Renk / İkon**: Görsel özelleştirme
- **Aktif/Pasif**: Gösterilip gösterilmeyeceği

### 3. Dashboard Entegrasyonu

Ana panel kontrolleri widget listesini otomatik çeker ve grid layout'ta gösterir.

### 4. Güvenlik

SQL sorguları yalnızca SELECT ile sınırlıdır. INSERT/UPDATE/DELETE içeren sorgular engellenir.

## Rota Yapısı

| Rota                                    | Açıklama                      |
| --------------------------------------- | ----------------------------- |
| `backend/dashboard-widgets`             | Widget listesi                |
| `backend/dashboard-widgets/create`      | Yeni widget oluştur           |
| `backend/dashboard-widgets/update/{id}` | Widget düzenle                |
| `backend/dashboard-widgets/delete/{id}` | Widget sil                    |
| `backend/dashboard-widgets/toggle/{id}` | Aktif/pasif                   |
| `backend/dashboard-widgets/reorder`     | Sıralama güncelle (drag-drop) |
