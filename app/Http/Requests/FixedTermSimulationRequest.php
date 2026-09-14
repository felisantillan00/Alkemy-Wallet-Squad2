<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class FixedTermSimulationRequest extends FormRequest
{
    # CUALQUIER USUARIO AUTENTICADO PUEDE SIMULAR UN PLAZO FIJO
    public function authorize(): bool
    {
        return true;
    }

    # EL CLIENTE INDICA EL PLAZO EN DIAS O UNA FECHA DE FINALIZACION, NO AMBOS
    public function rules(): array
    {
        return [
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'term_days' => ['nullable', 'integer'],
            'end_date'  => ['nullable', 'date', 'after:today'],
        ];
    }

    # MENSAJES DE ERROR EN ESPAÑOL
    public function messages(): array
    {
        return [
            'amount.required'   => 'El monto es obligatorio.',
            'amount.numeric'    => 'El monto debe ser un número.',
            'amount.min'        => 'El monto debe ser mayor que 0.',
            'term_days.integer' => 'El plazo debe ser una cantidad entera de días.',
            'end_date.date'     => 'La fecha de finalización no es válida.',
            'end_date.after'    => 'La fecha de finalización debe ser posterior a hoy.',
        ];
    }

    # VALIDACIONES CRUZADAS: EXACTAMENTE UNO DE term_days / end_date, Y DENTRO DEL RANGO PERMITIDO
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if ($validator->errors()->has('end_date') || $validator->errors()->has('term_days')) {
                return;
            }

            $tieneDias = $this->filled('term_days');
            $tieneFecha = $this->filled('end_date');

            if ($tieneDias && $tieneFecha) {
                $validator->errors()->add('term_days', 'Indicá el plazo en días o la fecha de finalización, no ambos.');

                return;
            }

            if (! $tieneDias && ! $tieneFecha) {
                $validator->errors()->add('term_days', 'Indicá el plazo en días o la fecha de finalización.');

                return;
            }

            $dias = $this->plazoEnDias();
            $min = config('investments.fixed_term.min_days');
            $max = config('investments.fixed_term.max_days');

            if ($dias < $min || $dias > $max) {
                $validator->errors()->add('term_days', "El plazo debe ser de entre {$min} y {$max} días.");
            }
        });
    }

    # PLAZO EN DIAS, YA SEA EL INFORMADO DIRECTAMENTE O EL CALCULADO DESDE end_date
    public function plazoEnDias(): int
    {
        if ($this->filled('term_days')) {
            return (int) $this->input('term_days');
        }

        return (int) now()->startOfDay()->diffInDays(Carbon::parse($this->input('end_date'))->startOfDay());
    }

    # FECHA DE FINALIZACION, YA SEA LA INFORMADA DIRECTAMENTE O LA CALCULADA DESDE term_days
    public function fechaFinalizacion(): Carbon
    {
        if ($this->filled('end_date')) {
            return Carbon::parse($this->input('end_date'))->startOfDay();
        }

        return now()->startOfDay()->addDays($this->plazoEnDias());
    }
}
