<?php

namespace App\Services;

use OpenAI;
use Exception;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private $client;
    private $prompt;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
        $this->prompt = $this->getPrompt();
    }

    public function processProduct(string $manufacturer, string $partNumber): array
    {
        try {
            $userPrompt = "Brand: {$manufacturer}\nModel Number: {$partNumber}";

            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini-search-preview',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->prompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'max_tokens' => 1000,
            ]);

            $content = $response->choices[0]->message->content;
            Log::info("OpenAI RAW response : " . ($content ?? 'NULL'));

            if ($content === null || empty(trim($content))) {
                return [
                    'success' => false,
                    'error' => 'OpenAI returned empty or null content',
                    'data' => null,
                    'raw_response' => $content,
                    'usage' => $response->usage->toArray(),
                ];
            }

            return [
                'success' => true,
                'data' => $this->parseResponse($content),
                'raw_response' => $content,
                'usage' => $response->usage->toArray(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null,
                'raw_response' => null,
                'usage' => null,
            ];
        }
    }

    private function parseResponse(string $response): array
    {
        $raw = [];
        $clean = function ($text) {
            return trim(preg_replace([
                '/^[\-\•\*]\s*/m',   // remove leading -, *, • bullets
                '/[*_`]+/',          // remove markdown formatting
            ], '', $text));
        };

    // UPC
    if (preg_match('/\*\*UPC:\*\*\s*(.+)/', $response, $match)) {
        $raw['upc'] = trim($match[1]);
    }

    // Title
    if (preg_match('/\*\*Title:\*\*\s*(.+)/', $response, $match)) {
        $raw['title'] = trim($match[1]);
    }

    // Meta Description
    if (preg_match('/\*\*Meta Description:\*\*\s*(.*?)\n\n/s', $response, $match)) {
        $raw['meta_description'] = trim($match[1]);
    }

    // Product Description
    if (preg_match('/\*\*Product Description:\*\*\s*(.*?)\n\n\*\*Bullet Points:/s', $response, $match)) {
        $raw['product_description'] = trim($match[1]);
    }

    // Bullet Points
    if (preg_match('/\*\*Bullet Points:\*\*\n(.*?)\n\n\*\*Key Specs:/s', $response, $match)) {
        $bullets = array_filter(array_map('trim', explode("\n", $match[1])));
        $raw['bullet_points'] = $bullets;
    }

    // Key Specs (excluding UPC)
    if (preg_match('/\*\*Key Specs:\*\*\n(.*?)\n\n\*\*Main Category:/s', $response, $match)) {
        $lines = array_filter(array_map('trim', explode("\n", $match[1])));
        $specs = [];

        foreach ($lines as $line) {
            // Skip Lines if its N/A or empty
            if (empty($line) || stripos($line, 'N/A') !== false) {
                continue;
            }
            if (stripos($line, 'UPC:') === false) {
                $specs[] = $line;
            }
        }

        $raw['key_specs'] = $specs;
    }

    // Main Category
    if (preg_match('/\*\*Main Category:\*\*\s*(.+)/', $response, $match)) {
        $raw['main_category'] = trim($match[1]);
    }

    // Subcategory
    if (preg_match('/\*\*Subcategory:\*\*\s*(.+)/', $response, $match)) {
        $raw['subcategory'] = trim($match[1]);
    }

    // Type Category
    if (preg_match('/\*\*Type Category:\*\*\s*(.+)/', $response, $match)) {
        $raw['type_category'] = trim($match[1]);
    }

    // Keywords
    if (preg_match('/\*\*Keywords:\*\*\s*(.+)/', $response, $match)) {
        $raw['keywords'] = array_map('trim', explode(',', $match[1]));
    }
    return [
        'upc' => isset($raw['upc']) ? $clean($raw['upc']) : '',
        'title' => isset($raw['title']) ? $clean($raw['title']) : '',
        'meta_description' => isset($raw['meta_description']) ? $clean($raw['meta_description']) : '',
        'product_description' => isset($raw['product_description']) ? $clean($raw['product_description']) : '',
        'bullet_points' => isset($raw['bullet_points']) ? array_map($clean, $raw['bullet_points']) : [],
        'key_specs' => isset($raw['key_specs'])
            ? array_filter(array_map(function ($line) use ($clean) {
                return stripos($line, 'UPC:') === false ? $clean($line) : null;
            }, $raw['key_specs']))
            : [],
        'main_category' => isset($raw['main_category']) ? $clean($raw['main_category']) : '',
        'subcategory' => isset($raw['subcategory']) ? $clean($raw['subcategory']) : '',
        'type_category' => isset($raw['type_category']) ? $clean($raw['type_category']) : '',
        'keywords' => isset($raw['keywords']) ? array_map($clean, $raw['keywords']) : [],
    ];
    }

    private function getPrompt(): string
    {
        return 'Instruction:
I am building a professional eCommerce catalog. Please return a high-quality, SEO-optimized, shopper-friendly product listing for the product I provide.

✅ Always return **all** of the following fields — in this **exact order**, with these **exact headings**. Do not omit any field. The **UPC must be returned as a standalone field and is required**.

🛑 Do not reorder, rename, skip, or merge sections.
🛑 Do not place the UPC inside Key Specs — it must be the first, separate heading.

📦 Use reputable manufacturer and retail sources to find accurate product information. Match only the full model number exactly. Use multiple sources to ensure accuracy.

✍️ Format your output **exactly like this** (with the same section titles and order):

---

**UPC:** [value]

**Title:** [value]

**Meta Description:**
[value]

**Product Description:**
[value — 120 to 250+ words, original, well-written, helpful to shoppers. Include materials, design, features, use cases, etc.]

**Bullet Points:**

- [concise feature or benefit]
- [concise feature or benefit]
- [concise feature or benefit]
- [concise feature or benefit]
- [concise feature or benefit]

**Key Specs:**
Include technical and physical specifications relevant to shoppers, such as:
Material
Build
Power Source
Carry Style
Output
Size
Compatibility
Orientation (if applicable)
Modes (if applicable)
Country of Origin (if known)
Etc.
the max characters length for each spec is 50-80 characters only.
- **[Spec Label]:** [Value]
- **[Spec Label]:** [Value]
- (Minimum 6, up to 12 relevant specs — no UPC here)

**Main Category:** [value]
**Subcategory:** [value]
**Type Category:** [value]

**Keywords:** [comma-separated list of SEO-relevant search terms real buyers would use]

---

🔒 All content must be 100% unique, SEO-friendly, and written for humans.
Do not copy or paraphrase manufacturer or distributor content.
Focus on what helps the shopper buy and improves search visibility.

I will provide one products brand and model number below.
';
    }
}
