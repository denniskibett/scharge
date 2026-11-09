<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A photo of a house. `applies_to`: unit (just that house), type (all houses of the same
 * unit_type in the property) or property (every house there). See 2026_11_09_unit_photos.sql.
 */
class UnitPhoto extends Model
{
    protected $table = 'unit_photos';

    public const APPLIES_TO = ['unit', 'type', 'property'];

    protected $fillable = ['company_id', 'property_id', 'unit_id', 'unit_type', 'applies_to', 'path', 'caption', 'sort', 'uploaded_by'];

    /** Photos that show for a house: its own, its type's, then the property's. */
    public static function forUnit(object $unit): Builder
    {
        return static::where('property_id', $unit->property_id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('applies_to', 'unit')->where('unit_id', $unit->id))
                ->orWhere(fn ($w) => $w->where('applies_to', 'type')->where('unit_type', $unit->unit_type))
                ->orWhere('applies_to', 'property'))
            ->orderByRaw("FIELD(applies_to, 'unit', 'type', 'property')")->orderBy('sort')->orderBy('id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
