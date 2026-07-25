<?php

namespace Modules\Notifications\Libraries;

/**
 * Anlık-sinyal deposu sözleşmesi (SSE nudge sayaçları için dar arayüz).
 *
 * RealtimeSignal (final) bunu uygular; RealtimeChannel ve SSE stream() bu tipe
 * bağımlıdır, böylece testler canlı Redis olmadan bir test double enjekte edebilir
 * (Services::signalStore() seam'i). Uygulamalar ASLA istisna sızdırmaz — bump false,
 * read baseline-0 döner.
 */
interface SignalStoreInterface
{
    /**
     * Bir kanalın sinyal sayacını artırır (best-effort).
     *
     * @param string $channel Notifier::topicFor() çıktısı olan kanal adı.
     *
     * @return bool Sayaç artırılabildiyse true; aksi halde false.
     */
    public function bump(string $channel): bool;

    /**
     * Verilen kanalların güncel sinyal sayaçlarını okur (yoksa/erişilemezse 0).
     *
     * @param list<string> $channels Notifier::topicsFor() ile türetilen kanal adları.
     *
     * @return array<string, int> channel => güncel sayaç (yoksa 0).
     */
    public function read(array $channels): array;
}
