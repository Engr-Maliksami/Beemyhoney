<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Request;
use App\Models\FacebookWebhookCall;
use Exception;
use Livewire\WithPagination;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\EscposImage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;



class Comments extends Component
{
    use WithPagination;

    public $searchClient;
    public $selectedIds = [];
    public $selectAll = false;
    public $searchText = "";
    public $selectedSource = "";
    public $selectedDateRange;
    public $qrCode = null;
    public $selectedComment;
    public $qrCodeSvg;
    public $searchCustomerId;

    public $qrCodeModel = null;
    public function mount()
    {
        $this->searchCustomerId = Request::get('FBId', 0);
    }

    public function updatedSearchClient()
    {
        $this->resetPage();
    }

    public function getUserComments()
    {
        return FacebookWebhookCall::with(['customer', 'facebookPage'])
            ->where('item_type', 'comment')
            ->where('cus_fb_id', '!=', '106735618295724')
            ->when($this->searchText, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_id', 'like', '%' . $this->searchText . '%')
                        ->orWhere('cus_fb_id', 'like', '%' . $this->searchText . '%')
                        ->orWhere('cus_fb_name', 'like', '%' . $this->searchText . '%')
                        ->orWhere('message', 'like', '%' . $this->searchText . '%');
                });
            })
            ->when($this->selectedSource, function ($query) {
                if ($this->selectedSource == 'with') {
                    $query->whereNotNull('order_id');
                } else {
                    $query->whereNull('order_id');
                }
            })
            ->when($this->searchCustomerId, function ($query) {
                $query->where('cus_fb_id', $this->searchCustomerId);
            })
            ->when($this->selectedDateRange, function ($query) {
                $dates = explode(' to ', $this->selectedDateRange);
                if (count($dates) === 2) {
                    $query->whereBetween('created_at', [$dates[0], $dates[1]]);
                } elseif (count($dates) === 1) {
                    $query->whereDate('created_at', $dates[0]);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);
    }





    // This method generates the QR code when the button is clicked.
    // public function generate($id)
    // {
    //     $comment =   FacebookWebhookCall::with(['customer', 'facebookPage'])
    //         ->where('id', $id)->first();

    //     $data = json_encode($comment);
    //     $$qrCode = QrCode::format('png')->size(200)->generate($data);
    //     $tempFile = tempnam(sys_get_temp_dir(), 'qrcode') . '.png';
    //     file_put_contents($tempFile, $qrCode);
    //     if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    //         exec('print ' . escapeshellarg($tempFile));
    //     } else {
    //         exec("lpr " . escapeshellarg($tempFile));
    //     }
    //     unlink($tempFile);
    // }

    // public function generate($id)
    // {
    //     $comment = FacebookWebhookCall::with(['customer', 'facebookPage'])
    //         ->where('id', $id)
    //         ->first();

    //     if (!$comment) {
    //         throw new \Exception("Record not found");
    //     }

    //     $rawData = json_encode($comment->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    //     $compressed = gzcompress($rawData, 9);
    //     $encoded = base64_encode($compressed);

    //     $chunkSize = 2000;
    //     $chunks = str_split($encoded, $chunkSize);

    //     foreach ($chunks as $index => $chunk) {
    //         $dataForQr = "PART:" . ($index + 1) . "/" . count($chunks) . "|" . $chunk;

    //         $qrCode = \QrCode::format('png')->size(300)->generate($dataForQr);

    //         $tempFile = tempnam(sys_get_temp_dir(), 'qrcode') . '.png';
    //         file_put_contents($tempFile, $qrCode);

    //         if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    //             \exec('print ' . escapeshellarg($tempFile));
    //         } else {
    //             \exec("lpr " . escapeshellarg($tempFile));
    //         }

    //         unlink($tempFile);
    //     }
    // }

    // public function generate($id)
    // {
    //     $comment = FacebookWebhookCall::with(['customer', 'facebookPage'])
    //         ->where('id', $id)
    //         ->first();

    //     if (!$comment) {
    //         throw new \Exception("Record not found");
    //     }

    //     dd($comment);
    //     $rawData = json_encode($comment->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    //     $compressed = gzcompress($rawData, 9);
    //     $encoded = base64_encode($compressed);

    //     $qrCode = \QrCode::format('png')->size(300)->generate($encoded);

    //     $fileName = "qrcode_" . $id . ".png";
    //     $tempFile = tempnam(sys_get_temp_dir(), 'qrcode') . '.png';
    //     file_put_contents($tempFile, $qrCode);

    //     return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    // }
    // public function generate($id)
    // {
    //     $comment = FacebookWebhookCall::with(['customer', 'facebookPage'])
    //         ->where('id', $id)
    //         ->first();

    //     if (!$comment) {
    //         throw new \Exception("Record not found");
    //     }

    //     $data = [
    //         "id"         => $comment->id,
    //         "page_id"    => $comment->page_id,
    //         "post_id"    => $comment->post_id,
    //         "comment_id" => $comment->comment_id,
    //         "message"    => $comment->message,
    //         "ip"         => $comment->ip,
    //         "status"     => $comment->status,
    //         "created_at" => $comment->created_at,
    //         "updated_at" => $comment->updated_at,
    //     ];

    //     $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

    //     // Generate QR code as binary PNG
    //     $qrCode = QrCode::format('png')
    //         ->size(300)
    //         ->encoding('UTF-8')
    //         ->generate($jsonData);

    //     // Save file in storage/app/public/qrcodes/
    //     $fileName = 'qrcode_' . $comment->id . '.png';
    //     $filePath = storage_path('app/public/qrcodes/' . $fileName);

    //     // Ensure directory exists
    //     if (!file_exists(dirname($filePath))) {
    //         mkdir(dirname($filePath), 0755, true);
    //     }

    //     file_put_contents($filePath, $qrCode);

    //     // Download response
    //     return response()->download($filePath)->deleteFileAfterSend(true);
    // }

    // public function generate($id)
    // {
    //     $comment = FacebookWebhookCall::with(['customer', 'facebookPage'])
    //         ->where('id', $id)
    //         ->first();

    //     if (!$comment) {
    //         throw new \Exception("Record not found");
    //     }

    //     // Data for QR code
    //     $data = [
    //         "id"         => $comment->id,
    //         "page_id"    => $comment->page_id,
    //         "post_id"    => $comment->post_id,
    //         "comment_id" => $comment->comment_id,
    //         "message"    => $comment->message,
    //         "ip"         => $comment->ip,
    //         "status"     => $comment->status,
    //         "created_at" => $comment->created_at,
    //         "updated_at" => $comment->updated_at,
    //     ];

    //     $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

    //     // Generate QR as PNG binary
    //     $qrCode = QrCode::format('png')
    //         ->size(250)
    //         // ->margin(1)
    //         ->encoding('UTF-8')
    //         ->generate($jsonData);

    //     // Make QR image
    //     $qrImage = Image::make($qrCode);

    //     // Create a blank canvas (white background)
    //     $canvas = Image::canvas(600, 400, '#ffffff');

    //     // Insert QR code into canvas
    //     $canvas->insert($qrImage, 'left', 20, 20);

    //     // Add text info on the right side
    //     $canvas->text("Name: " . ($comment->customer->name ?? 'Unknown'), 300, 60, function ($font) {
    //         $font->file(public_path('fonts/arial.ttf')); // Ensure font exists
    //         $font->size(24);
    //         $font->color('#000000');
    //     });

    //     $canvas->text("Message: " . $comment->message, 300, 120, function ($font) {
    //         $font->file(public_path('fonts/arial.ttf'));
    //         $font->size(20);
    //         $font->color('#000000');
    //     });

    //     $canvas->text("Date: " . $comment->created_at->format('d-m-Y H:i'), 300, 180, function ($font) {
    //         $font->file(public_path('fonts/arial.ttf'));
    //         $font->size(20);
    //         $font->color('#333333');
    //     });

    //     // Save final label
    //     $fileName = 'qrcode_label_' . $comment->id . '.png';
    //     $filePath = storage_path('app/public/qrcodes/' . $fileName);

    //     $canvas->save($filePath);

    //     return response()->download($filePath)->deleteFileAfterSend(true);
    // }

    // public function generate($id)
    // {
    //   $selectedComment = FacebookWebhookCall::with(['customer', 'facebookPage'])
    //         ->find($id);

    //     if ($selectedComment) {
    //         $this->dispatchBrowserEvent('alert', ['message' => 'Record not found']);
    //         return;
    //     }

    //     $data = [
    //         "id"         => $this->selectedComment->id,
    //         "page_id"    => $this->selectedComment->page_id,
    //         "post_id"    => $this->selectedComment->post_id,
    //         "comment_id" => $this->selectedComment->comment_id,
    //         "message"    => $this->selectedComment->message,
    //         "ip"         => $this->selectedComment->ip,
    //         "status"     => $this->selectedComment->status,
    //         "created_at" => $this->selectedComment->created_at,
    //         "updated_at" => $this->selectedComment->updated_at,
    //     ];

    //     $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

    //     $qrCode = QrCode::format('svg')
    //         ->size(300)
    //         ->encoding('UTF-8')
    //         ->generate($jsonData);
    //         dd($qrCode);
    //         // $this->qrCodeSvg=$qrCode;
    //         // dd($this->qrCodeSvg);

    // }
    
    protected $listeners = ['markCommentProcessed'];

    public function markCommentProcessed($id)
    {
        $comment = FacebookWebhookCall::find($id);
        if ($comment) {
            $comment->status = 'processed';
            $comment->save();
        }
    }

    public function generate($id)
    {
        $comment = FacebookWebhookCall::with(['customer', 'facebookPage'])
            ->where('id', $id)
            ->first();

        if (!$comment) {
            throw new \Exception("Record not found");
        }

        $data = [
            "id"         => $comment->id,
            "page_id"    => $comment->page_id,
            "cus_fb_name"    => $comment->cus_fb_name,
            // "comment_id" => $comment->comment_id,
            // "message"    => $comment->message,
            // "ip"         => $comment->ip,
            // "status"     => $comment->status,
            // "created_at" => $comment->created_at,
            // "updated_at" => $comment->updated_at,
        ];

        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        // Generate QR code as SVG string
        $qrCode = QrCode::format('svg')
            ->size(300)
            ->encoding('UTF-8')
            ->generate($jsonData);

        // Define public path (inside /public/qrcodes/)
        $fileName = 'qrcode_' . $comment->id . '.svg';
        $filePath = public_path('qrcodes/' . $fileName);

        // Ensure directory exists
        if (!file_exists(public_path('qrcodes'))) {
            mkdir(public_path('qrcodes'), 0755, true);
        }

        // Save the SVG file
        file_put_contents($filePath, $qrCode);

        // Pass path to Blade
        $this->qrCodeModel = $fileName;
        $this->selectedComment = $comment->toArray();
        
        $this->dispatchBrowserEvent('start-print',['id'=>$comment->id]);
    }

    public function printPdf()
    {
        $pdf = Pdf::loadView('pdf.qrcode', [
            'comment' => $this->selectedComment,
            'qrCodeModel' => $this->qrCodeModel,
        ]);
        

        return response()->streamDownload(
            fn() => print($pdf->output()),
            'qrcode_' . $this->selectedComment['id'] . '.pdf'
        );
    }




    public function render()
    {
        return view('livewire.comments', [
            'UserComments' => $this->getUserComments()
        ]);
    }
}
