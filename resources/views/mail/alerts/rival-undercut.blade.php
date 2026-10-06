فروشگاه: {{ $shop->name }}

محصول «{{ $product->name }}» ({{ $product->external_id }}):

- قیمت شما: {{ $product->price }}
- ارزان‌ترین رقیب: {{ $snapshot->cheapest_price }}
- میانه رقبا: {{ $snapshot->median_price ?? '—' }}
- تعداد: {{ $snapshot->competitor_count }}

@if ($product->cannot_match_profitably)
رقیب از کف هزینه شما پایین‌تر است — همسان‌سازی سودآور نیست.
@endif

توصیه کف‌آگاه: {{ $product->recommended_price }}
