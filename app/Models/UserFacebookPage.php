<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookPage extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_pages';

    protected $attributes = [
        't_customer' => "Welcome [Customer Name]!\n\nThank you for joining us. We are thrilled to have you with us.\n\nHere are the rules for our Facebook Live:\n1. Be respectful to everyone.\n2. Have fun and enjoy the session.\n3. Feel free to ask any questions during the live session.\n\nIf you have any concerns, reach out to our support team. Thank you!",
        't_address' => "Hi [Customer Name],\n\nCould you please share your complete address in the following format?\n\nStreet Address:\nCity:\nState:\nZIP Code:\nCountry:\n\nThis will help us process your order smoothly. Let us know if you face any issues.",
        't_order' => "Good news, [Customer Name]!\n\nYour order #[Order Number] has been approved successfully.\n\nTotal Amount: [Total Amount]\n\nWe will notify you once it's shipped. Thank you for shopping with us!",
        't_invoice' => "Dear [Customer Name],\n\nHere is your invoice #[Invoice Number].\n\nPlease find the attached invoice for your records.\n\nTotal Amount: [Total Amount]\n\nLet us know if you have any questions. We are here to help!",
        't_shipped' => "Hello [Customer Name],\n\nGreat news! Your order #[Order Number] has been shipped.\n\nHere are the shipping details:\nTracking ID: [Tracking ID]\nCourier: [Courier Name]\nEstimated Delivery Date: [Delivery Date]\n\nTrack your package here: [Tracking Link]\n\nThank you for choosing us. We hope to serve you again soon!",
        't_comment' => "Welcome [Customer Name]!\n\nThank you for commenting on our live session. We'll contact you soon regarding your order. Feel free to let us know if you have any questions."
    ];

    protected $fillable = [
        'facebook_id',
        'page_id',
        'name',
        'cover_url',
        'email',
        'username',
        'page_access_token',
        't_customer',
        't_address',
        't_order',
        't_invoice',
        't_shipped',
        't_comment',
        'bot_enabled'
    ];

    public function facebookAccount()
    {
        return $this->belongsTo(UserFacebookAccount::class);
    }
}
