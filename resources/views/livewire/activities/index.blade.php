{{-- Aktivitaeten eines Datensatzes: Verlauf plus Eingabefeld fuer eine Notiz.

     Die Ansicht steht meist in einer schmalen Seitenspalte (w-80), nicht auf
     einer breiten Seite. Deshalb keine Karten mit Kopfzeile, Rahmen und
     Schatten je Eintrag - das war bei 300px Breite fast nur noch Rahmen -,
     sondern eine ruhige Zeitleiste mit Haarlinien, im nx-Ton wie der Rest der
     Plattform.

     Drei Dinge waren inhaltlich falsch und sind mitgegangen:

     1. Das Bild kam aus profile_photo_url. Das Nutzer-Modell der Plattform hat
        diese Eigenschaft nicht (es heisst avatarUrl()), also stand dort eine
        leere Quelle - und der Browser malte sein kaputtes Bild hin. Jetzt
        x-nx-avatar: Bild, wenn es eines gibt, sonst die Initiale.

     2. Systemeintraege haben keinen Nutzer. Beim Drucker ist das der
        Normalfall: Das Geraet fragt per CloudPRNT an, ohne angemeldet zu sein.
        Die Kopfzeile blieb dann komplett leer. Jetzt steht dort "System" mit
        einem Zahnrad.

     3. Der Block "Vorher / Neu" las properties['old'] und properties['new'].
        Diese Schluessel schreibt niemand - LogsActivity legt die geaenderten
        Felder flach ab. Der Block konnte also nie erscheinen, und was sich
        geaendert hat, war nirgends zu sehen. Jetzt werden die Felder selbst
        gezeigt, eingeklappt hinter "N Felder".
--}}
<div class="space-y-4">

    {{-- Eingabe --}}
    <div class="flex items-start gap-2">
        <textarea
            wire:model="message"
            rows="1"
            placeholder="Notiz schreiben …"
            class="flex-1 resize-none rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-3 py-1.5 text-sm text-[color:var(--nx-text)] placeholder-[color:var(--nx-faint)] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--nx-line-strong)]"
        ></textarea>
        <x-nx-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">Senden</x-nx-button>
    </div>

    {{-- Verlauf --}}
    <ul class="m-0 divide-y divide-[color:var(--nx-line)] p-0">
        @forelse ($activities as $activity)
            @php
                $nutzer = $activity->user;
                // avatarUrl() ist der Weg des Plattform-Nutzers; profile_photo_url
                // bleibt als Rueckfallebene fuer fremde Nutzer-Modelle stehen.
                $bild = $nutzer
                    ? (method_exists($nutzer, 'avatarUrl') ? $nutzer->avatarUrl() : ($nutzer->profile_photo_url ?? null))
                    : null;

                $wer = $nutzer?->name ?: 'System';

                // Was passiert ist - in Worten statt als Eventname. Unbekanntes
                // wird lesbar gemacht statt verschluckt.
                $was = match ($activity->name) {
                    'created'       => 'angelegt',
                    'updated'       => 'geändert',
                    'deleted'       => 'gelöscht',
                    'restored'      => 'wiederhergestellt',
                    'message_added', 'note' => null,
                    default         => \Illuminate\Support\Str::of((string) $activity->name)->replace('_', ' ')->lower()->toString(),
                };

                // Nur echte Werte zeigen: Ein Feld, das von null auf null geht,
                // sagt nichts, und verschachtelte Strukturen gehoeren nicht in
                // eine 300px-Spalte.
                $felder = collect($activity->properties ?? [])
                    ->reject(fn ($wert) => $wert === null || $wert === '' || is_array($wert))
                    ->map(fn ($wert) => is_bool($wert) ? ($wert ? 'ja' : 'nein') : \Illuminate\Support\Str::limit((string) $wert, 80));
            @endphp

            <li class="flex gap-2.5 py-3 last:pb-0" wire:key="activity-id-{{ $activity->id }}">
                @if ($nutzer)
                    <x-nx-avatar size="sm" :name="$nutzer->name" :src="$bild" class="mt-0.5" />
                @else
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-faint)]" title="System">
                        @svg('heroicon-o-cog-6-tooth', 'w-3.5 h-3.5')
                    </span>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-1.5">
                        <span class="truncate text-xs font-medium text-[color:var(--nx-text)]">{{ $wer }}</span>
                        @if ($was)
                            <span class="truncate text-xs text-[color:var(--nx-muted)]">{{ $was }}</span>
                        @endif
                        <span class="ml-auto shrink-0 text-[11px] text-[color:var(--nx-faint)]" title="{{ $activity->created_at?->format('d.m.Y H:i:s') }}">
                            {{ $activity->created_at?->diffForHumans() }}
                        </span>
                    </div>

                    @if ($activity->message)
                        <p class="m-0 mt-1 whitespace-pre-line text-sm leading-snug text-[color:var(--nx-text)]">{{ $activity->message }}</p>
                    @endif

                    @if ($felder->isNotEmpty())
                        {{-- Eingeklappt: Bei "angelegt" stehen hier alle Spalten
                             des Datensatzes. Aufgeklappt waere der Verlauf nicht
                             mehr zu ueberblicken, weggelassen fehlte er, wenn man
                             ihn braucht. --}}
                        <details class="mt-1">
                            <summary class="cursor-pointer text-[11px] text-[color:var(--nx-faint)] transition-colors hover:text-[color:var(--nx-text)]">
                                {{ $felder->count() }} {{ $felder->count() === 1 ? 'Feld' : 'Felder' }}
                            </summary>
                            <dl class="m-0 mt-1 space-y-0.5">
                                @foreach ($felder as $schluessel => $wert)
                                    <div class="flex gap-1.5 text-[11px]">
                                        <dt class="shrink-0 text-[color:var(--nx-faint)]">{{ $schluessel }}</dt>
                                        <dd class="m-0 min-w-0 flex-1 break-all font-mono text-[color:var(--nx-muted)]">{{ $wert }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </div>
            </li>
        @empty
            <li>
                <x-nx-empty icon="heroicon-o-chat-bubble-left-right">
                    Noch keine Aktivitäten
                    <x-slot name="action">
                        <span class="text-[11px] text-[color:var(--nx-faint)]">Änderungen und Notizen erscheinen hier</span>
                    </x-slot>
                </x-nx-empty>
            </li>
        @endforelse
    </ul>
</div>
