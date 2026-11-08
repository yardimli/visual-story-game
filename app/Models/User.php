<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected static function booted(): void {
        static::created(fn(User $user)=>$user->tokenCredits()->firstOrCreate(['grant_key'=>'signup'],['amount'=>1000000,'source'=>'signup','description'=>'Welcome credit']));
    }
    public function tokenCredits(){return $this->hasMany(TokenCredit::class);}
    public function creditTotal(): int {return (int)$this->tokenCredits()->sum('amount');}
    public function availableCredit(): int {return max(0,$this->creditTotal()-$this->llm_spent-$this->llm_reserved);}


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'openrouter_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'openrouter_key' => 'encrypted',
        'llm_spent' => 'integer',
        'llm_reserved' => 'integer',
    ];
}
