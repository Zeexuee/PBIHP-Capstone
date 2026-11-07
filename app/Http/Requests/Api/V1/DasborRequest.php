<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\KelompokKomoditas;
use App\Enums\Pulau;
use App\Enums\TingkatHarga;
use App\Models\Komoditas;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi bersama untuk endpoint dasbor yang menerima pilihan komoditas,
 * tingkat harga, dan pulau.
 */
abstract class DasborRequest extends FormRequest
{
    private ?Komoditas $komoditasTerpilih = null;

    /**
     * Endpoint dasbor bersifat publik dan hanya membaca data.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'komoditas' => ['required', 'string', Rule::exists('komoditas', 'kode')],
            'tingkat' => ['nullable', Rule::enum(TingkatHarga::class)],
            'pulau' => ['nullable', Rule::enum(Pulau::class)],
        ];
    }

    /**
     * Harga produsen hanya dicatat untuk beras.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['komoditas', 'tingkat'])) {
                    return;
                }

                if ($this->tingkat() === TingkatHarga::Produsen && $this->komoditas()->kelompok !== KelompokKomoditas::Beras) {
                    $validator->errors()->add('tingkat', 'Harga produsen hanya tersedia untuk beras.');
                }
            },
        ];
    }

    /**
     * Aturan berbentuk objek seperti Rule::enum dicari dengan kunci
     * "parameter.enum", bukan "enum", sehingga kuncinya memakai pola *.enum.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'string' => ':Attribute harus berupa teks.',
            'exists' => ':Attribute tidak dikenal.',
            '*.enum' => ':Attribute tidak valid.',
            'date_format' => ':Attribute harus berformat TTTT-BB-HH.',
            'after_or_equal' => ':Attribute tidak boleh lebih awal dari tanggal mulai.',
            'in' => ':Attribute tidak valid.',
            'array' => ':Attribute harus berupa daftar.',
            'distinct' => ':Attribute tidak boleh berulang.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'komoditas' => 'komoditas',
            'tingkat' => 'tingkat harga',
            'pulau' => 'pulau',
            'tanggal' => 'tanggal',
            'mulai' => 'tanggal mulai',
            'selesai' => 'tanggal selesai',
            'agregasi' => 'agregasi',
            'provinsi' => 'provinsi',
            'provinsi.*' => 'provinsi',
        ];
    }

    public function komoditas(): Komoditas
    {
        return $this->komoditasTerpilih ??= Komoditas::where('kode', $this->string('komoditas')->toString())->sole();
    }

    public function tingkat(): TingkatHarga
    {
        return $this->enum('tingkat', TingkatHarga::class) ?? TingkatHarga::Konsumen;
    }

    public function pulau(): ?Pulau
    {
        return $this->enum('pulau', Pulau::class);
    }
}
