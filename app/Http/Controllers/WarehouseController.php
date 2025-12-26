<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inbound;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class WarehouseController extends Controller
{
    /**
     * Login - returns a fake token (for now).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addHours(2)->toDateTimeString(),
        ]);
    }

    /**
     * Create inbound order.
     */
    public function createInbound(Request $request)
    {
        $request->validate([
            'doc_ext_id' => 'required|integer',
            'sender_name' => 'required|string',
            'delivery_date' => 'nullable|date',
            'products' => 'required|array',
            'attachments' => 'required|array',
        ]);

        $inbound = Inbound::create([
            'name' => 'Inbound-' . $request->doc_ext_id,
            'sender_name' => $request->sender_name,
            'carrier_name' => $request->carrier_name,
            'delivery_transport_no' => $request->carrier_transport_no,
            'doc_comment' => $request->doc_comment,
            'delivery_date' => $request->delivery_date,
        ]);

        // Attach products
        foreach ($request->products as $product) {
            $p = Product::firstOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'ean' => $product['ean'] ?? null,
                    'batch_number' => $product['batch_number'] ?? null,
                    'expire_date' => $product['expire_date'] ?? null,
                    'quantity_box' => $product['quantity_box'] ?? null,
                    'quantity_pallet' => $product['quantity_pallet'] ?? null,
                    'net_weight' => $product['net_weight'] ?? null,
                    'gross_weight' => $product['gross_weight'] ?? null,
                    'length' => $product['length'] ?? null,
                    'image_url' => $product['image'] ?? null,
                    'width' => $product['width'] ?? null,
                    'height' => $product['height'] ?? null,
                    'quantity_per_box' => $product['quantity_per_box'] ?? null,
                    'quantity_per_pallet' => $product['quantity_per_pallet'] ?? null,
                    'comment' => $product['comment'] ?? null,
                    'price' => $product['price'] ?? 0,
                ]
            );
            $inbound->products()->attach($p->id);
        }

        // Attach files
        foreach ($request->attachments as $att) {
            $a = Attachment::create([
                'file_name' => $att['file_name'],
                'files' => $att['file'],
                'description' => $att['description'] ?? null,
            ]);
            $inbound->attachments()->attach($a->id);
        }

        return response()->json(['message' => 'Inbound order created', 'data' => $inbound]);
    }

    /**
     * Create outbound order.
     */
    public function createOutbound(Request $request)
    {
        $request->validate([
            'order_ext_id' => 'required|integer',
            'recipient_name' => 'required|string',
            'recipient_street' => 'required|string',
            'recipient_city' => 'required|string',
            'recipient_post_code' => 'required|string',
            'recipient_country' => 'required|string',
            'recipient_email' => 'required|email',
            'recipient_tel' => 'required|string',
            'delivery_type_code' => 'required|string',
            'products' => 'required|array',
            'attachments' => 'required|array',
        ]);

        $outbound = Outbound::create($request->except(['products', 'attachments']));

        // Attach products
        foreach ($request->products as $product) {
            $p = Product::firstOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'ean' => $product['ean'] ?? null,
                    'batch_number' => $product['batch_number'] ?? null,
                    'expire_date' => $product['expire_date'] ?? null,
                    'quantity_box' => $product['quantity_box'] ?? null,
                    'quantity_pallet' => $product['quantity_pallet'] ?? null,
                    'net_weight' => $product['net_weight'] ?? null,
                    'gross_weight' => $product['gross_weight'] ?? null,
                    'image_url' => $product['image'] ?? null,
                    'length' => $product['length'] ?? null,
                    'width' => $product['width'] ?? null,
                    'height' => $product['height'] ?? null,
                    'quantity_per_box' => $product['quantity_per_box'] ?? null,
                    'quantity_per_pallet' => $product['quantity_per_pallet'] ?? null,
                    'comment' => $product['comment'] ?? null,
                    'price' => $product['price'] ?? 0,
                ]
            );
            $outbound->products()->attach($p->id);
        }

        // Attach files
        foreach ($request->attachments as $att) {
            $a = Attachment::create([
                'file_name' => $att['file_name'],
                'files' => $att['file'],
                'description' => $att['description'] ?? null,
            ]);
            $outbound->attachments()->attach($a->id);
        }

        return response()->json(['message' => 'Outbound order created', 'data' => $outbound]);
    }

    /**
     * Get stock (all or by sku).
     */
    public function getStocks($sku = null)
    {
        try {
            if ($sku) {
                $product = Product::where('sku', $sku)->firstOrFail();
                return response()->json($product);
            }
            return response()->json(Product::all());
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Product not found'], 404);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
