<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

/**
 * KALICI teslim yapan kanalların işaretçi (marker) arayüzü.
 *
 * Bir kanalın bu arayüzü uygulaması şu SÖZÜ verir: `ok` dönen her sonuç, bildirimin
 * kullanıcı yeniden bağlandığında da orada olacağı bir KAYIT bıraktığı anlamına gelir.
 * Bugün bunu yalnız {@see InAppChannel} yapar ({@see \Modules\Notifications\Libraries\Notifier}
 * Model B: `notifications` tablosuna küresel satır). {@see RealtimeChannel} ise satır
 * DEĞİL, yalnız "yeniden oku" sinyali üretir — teslim edilmiş sayılamaz.
 *
 * NEDEN AYRI BİR ARAYÜZ: "gönderildi mi" sorusunun cevabı "herhangi bir kanal ok döndü
 * mü" DEĞİLDİR. Kalıcı yazan kanal bir GÜVENLİK refüzüyle satırı atıp
 * ({@see InAppChannel::refuseUnenforceableExclusion()}) geçici kanal sinyalini
 * bump'ladığında, "herhangi biri ok" kuralı yöneticiye "gönderildi" der; oysa ortada
 * bildirim yoktur ve sinyali izleyen istemciler boş bir feed bulur.
 *
 * Metot EKLEMEZ: mevcut kanalların (ve test ikizlerinin) hiçbiri değişmek zorunda
 * kalmadan, YENİ kanallar da kalıcılığı açıkça beyan etmedikçe geçici sayılır
 * (fail-closed). Karar {@see \Modules\Notifications\Libraries\DispatchOutcome} içinde
 * bu işarete bakılarak verilir.
 */
interface DurableChannelInterface extends ChannelInterface
{
}
