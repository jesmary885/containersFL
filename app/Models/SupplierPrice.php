namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lista de precios del proveedor por combinación de tipo, medida,
 * condición y calidad.
 *
 * Cada cambio de precio es una fila nueva; no se sobrescribe la
 * anterior. Así queda el historial de cuánto costaba cada cosa en cada
 * momento, que es lo que permite explicar por qué el margen de marzo
 * fue distinto al de agosto (RB-029).
 */
class SupplierPrice extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price'       => 'decimal:2',
            'pickup_fee'  => 'decimal:2',

            /*
             | CAMBIO — el modelo decía 'valid_from' y 'valid_to'.
             | La tabla tiene 'quoted_at', 'valid_until' e 'is_current'.
             |
             | No es solo el nombre: es otra idea. La tabla no guarda un
             | rango cerrado, guarda "desde cuándo lo cotizaron y hasta
             | cuándo lo respetan", más una bandera para poder descartar
             | un precio a mano sin tocar las fechas.
             */
            'quoted_at'   => 'date',
            'valid_until' => 'date',
            'is_current'  => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function depot()     { return $this->belongsTo(Depot::class); }
    public function type()      { return $this->belongsTo(ContainerType::class, 'container_type_id'); }
    public function size()      { return $this->belongsTo(ContainerSize::class, 'container_size_id'); }
    public function condition() { return $this->belongsTo(ContainerCondition::class, 'container_condition_id'); }
    public function grade()     { return $this->belongsTo(ContainerGrade::class, 'container_grade_id'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * Precios vigentes a una fecha.
     *
     * Tres condiciones, en este orden:
     *   1. ya lo habían cotizado en esa fecha  (quoted_at <= fecha)
     *   2. todavía lo respetaban               (valid_until >= fecha, o sin límite)
     *   3. nadie lo descartó a mano            (is_current)
     *
     * El whereNull cubre el caso más común: el proveedor da un precio y
     * no dice hasta cuándo. Sin esa línea, esos precios —que son la
     * mayoría— quedarían fuera del resultado.
     */
    public function scopeValidOn(Builder $q, ?Carbon $date = null): Builder
    {
        $date ??= now();

        return $q->where('is_current', true)
            ->whereDate('quoted_at', '<=', $date)
            ->where(fn (Builder $s) => $s
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $date));
    }

    /** El precio que se usa hoy. Atajo del anterior. */
    public function scopeCurrent(Builder $q): Builder
    {
        return $q->validOn(now());
    }

    /**
     * Da de baja este precio porque llegó uno nuevo.
     *
     * Se llama antes de crear la fila nueva. No borra nada: solo baja
     * la bandera, para que el precio viejo siga estando disponible al
     * revisar una compra antigua.
     */
    public function supersede(): void
    {
        $this->update([
            'is_current'  => false,
            'valid_until' => $this->valid_until ?? now()->toDateString(),
        ]);
    }
}
