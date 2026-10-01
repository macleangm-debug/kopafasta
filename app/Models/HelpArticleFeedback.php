<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticleFeedback extends Model
{
    protected $table = 'help_article_feedback';

    protected $fillable = [
        'user_id',
        'customer_id',
        'category_key',
        'article_slug',
        'vote',
        'locale',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
