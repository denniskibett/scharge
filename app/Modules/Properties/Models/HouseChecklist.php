<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Properties\Models\Tenancy;
use App\Models\User;

class HouseChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenancy_id',
        'checklist_type',
        'room',
        'item',
        'condition_before',
        'condition_after',
        'notes',
        'photos',
        'status',
        'completed_by',
        'completed_at',
        'repair_cost',
        'deduct_from_deposit',
    ];

    protected $casts = [
        'photos' => 'array',
        'repair_cost' => 'decimal:2',
        'deduct_from_deposit' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getConditionBadgeClass($condition)
    {
        $map = [
            'excellent' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'good' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'fair' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'poor' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'damaged' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        ];
        return $map[$condition] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusBadgeClass($status)
    {
        $map = [
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'needs_repair' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        ];
        return $map[$status] ?? 'bg-gray-100 text-gray-800';
    }

    // Default checklist items for a unit
    public static function getDefaultChecklistItems()
    {
        return [
            ['room' => 'Living Room', 'items' => ['Walls', 'Floor', 'Ceiling', 'Windows', 'Doors', 'Lighting', 'Power Outlets']],
            ['room' => 'Kitchen', 'items' => ['Cabinets', 'Countertops', 'Sink', 'Stove', 'Fridge', 'Floor', 'Walls']],
            ['room' => 'Bedroom 1', 'items' => ['Walls', 'Floor', 'Ceiling', 'Windows', 'Doors', 'Closet', 'Lighting']],
            ['room' => 'Bedroom 2', 'items' => ['Walls', 'Floor', 'Ceiling', 'Windows', 'Doors', 'Closet', 'Lighting']],
            ['room' => 'Bathroom', 'items' => ['Shower/Bathtub', 'Toilet', 'Sink', 'Mirror', 'Floor', 'Walls', 'Ventilation']],
            ['room' => 'Hallway', 'items' => ['Walls', 'Floor', 'Ceiling', 'Doors']],
            ['room' => 'External', 'items' => ['Paint', 'Windows', 'Doors', 'Garden', 'Fence', 'Parking']],
        ];
    }
}