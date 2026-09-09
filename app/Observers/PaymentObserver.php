<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * OBSERVER DEL PAGO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Dos responsabilidades, una por evento:
 *
 *   creating()   Le pone el número (PAY-00001) y calcula los valores
 *                derivados que la pantalla no tiene por qué escribir a
 *                mano.
 *
 *   updated()    Si el estado del pago pasa de "confirmado" a uno de los
 *                que PaymentStatus::reversesAllocations() marca como
 *                inválido —cheque devuelto, tarjeta reembolsada o en
 *                disputa—, deshace TODO lo que ese pago tenía aplicado a
 *                facturas.
 *
 * ── POR QUÉ EN updated() Y NO EN updating() ──
 *
 * reverseAllAllocations() guarda cambios (con saveQuietly, que no
 * dispara observers) sobre las facturas y sobre el propio pago. Si esa
 * reversión corriera dentro de updating() —antes de que el UPDATE
 * original se confirme en la base— se mezclarían dos escrituras a la
 * misma fila del pago en la misma transacción, y es más fácil que algo
 * salga mal. Dejarlo para updated() significa: primero se guarda el
 * cambio de estado, después —ya con eso firme— se revierte lo demás.
 */


class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if (blank($payment->payment_number)) {
            $payment->payment_number = $payment->company->nextNumber('payment');
        }

        if (! isset($payment->net_amount) || $payment->net_amount === null) {
            $payment->net_amount = round((float) $payment->amount - (float) ($payment->fee_amount ?? 0), 2);
        }

        // Un pago recién creado, sin aplicar todavía a ninguna factura,
        // tiene el 100% de su monto disponible.
        if (! isset($payment->unapplied_amount) || $payment->unapplied_amount === null) {
            $payment->unapplied_amount = (float) $payment->amount;
        }

        if (blank($payment->received_at)) {
            $payment->received_at = now();
        }

        if (blank($payment->status)) {
            $payment->status = PaymentStatus::Completed;
        }
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $anterior = $payment->getOriginal('status');
        $anterior = $anterior instanceof PaymentStatus ? $anterior : PaymentStatus::tryFrom((string) $anterior);

        if ($anterior !== PaymentStatus::Completed) {
            // Solo interesa cuando SE DEJA de estar confirmado. Un pago
            // que nace 'pending' y pasa a 'failed' nunca llegó a
            // aplicarse a ninguna factura, así que no hay nada que
            // deshacer.
            return;
        }

        if ($payment->status->reversesAllocations()) {
            $payment->reverseAllAllocations();
        }
    }
}
