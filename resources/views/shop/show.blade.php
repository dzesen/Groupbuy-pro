<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }} - 拼团页面</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 防止 iOS 点击高亮 */
        * { -webkit-tap-highlight-color: transparent; }
        /* 增加底部安全距离适配 iPhone */
        .safe-area-pb { padding-bottom: env(safe-area-inset-bottom); }
    </style>
</head>
<body class="bg-gray-50 pb-32">

    <!-- 顶部状态栏 -->
    <div class="bg-white p-4 shadow-sm sticky top-0 z-10">
        <div class="flex justify-between items-center mb-2">
            <h1 class="text-lg font-bold text-gray-800 truncate pr-2">{{ $group->name }}</h1>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full whitespace-nowrap">团长: {{ $group->leader->name }}</span>
        </div>
        
        <!-- 进度条 -->
        <div class="w-full bg-gray-100 rounded-full h-3 mb-1 overflow-hidden">
            <div class="bg-rose-500 h-3 rounded-full transition-all duration-500" 
                 style="width: {{ $progress['percentage'] }}%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-500 font-medium">
            <span>当前: {{ $group->target_type == 'amount' ? '¥'.$progress['amount'] : $progress['quantity'].'件' }}</span>
            <span>目标: {{ $group->target_type == 'amount' ? '¥'.$group->target_value : $group->target_value.'件' }}</span>
        </div>
    </div>

    <!-- 错误/提示信息 -->
    @if(session('error'))
    <div class="bg-red-50 text-red-600 p-3 text-sm text-center font-medium">
        {{ session('error') }}
    </div>
    @endif

    <!-- 商品列表表单 -->
    <form action="{{ route('groups.join', $group->id) }}" method="POST" id="joinForm">
        @csrf
        <div class="p-4 space-y-4">
            @foreach($products as $product)
            <div class="bg-white p-4 rounded-xl border border-gray-200 flex gap-4 items-start shadow-sm hover:shadow-md transition">
                <!-- 商品图 -->
                <div class="w-20 h-20 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden relative">
                    @if($product->image_url)
                        <img src="{{ asset('storage/' . $product->image_url) }}" class="w-full h-full object-cover" alt="{{ $product->name }}">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-2xl text-gray-300">
                            🧸
                        </div>
                    @endif
                </div>
                
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-800 text-sm leading-tight mb-1">{{ $product->name }}</h3>
                    
                    <!-- ⭐⭐⭐ 修改开始：价格显示区域 ⭐⭐⭐ -->
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <!-- 人民币价格 -->
                        <div class="text-rose-600 font-bold text-lg">¥{{ $product->price }}</div>
                        
                        <!-- 原币价格 (如果有) -->
                        @if($product->original_price > 0)
                            <div class="text-xs text-gray-400">
                                ({{ floatval($product->original_price) }} {{ $product->currency }})
                            </div>
                        @endif
                    </div>
                    <!-- ⭐⭐⭐ 修改结束 ⭐⭐⭐ -->

                    <div class="flex justify-between items-center mt-2">
                        <div class="text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">
                            限购: {{ $product->limit_per_person }}
                        </div>
                    </div>
                </div>

                <!-- 数量加减器 -->
                <div class="flex flex-col items-end gap-1 self-center">
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="updateQty({{ $product->id }}, -1)" class="w-7 h-7 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-lg active:bg-gray-200 transition touch-manipulation pb-1">-</button>
                        
                        <input type="number" name="quantities[{{ $product->id }}]" id="qty-{{ $product->id }}" value="0" readonly class="w-8 text-center text-gray-800 font-bold bg-transparent border-none p-0 focus:ring-0">
                        
                        <button type="button" onclick="updateQty({{ $product->id }}, 1, {{ $product->limit_per_person }})" class="w-7 h-7 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center font-bold text-lg active:bg-rose-200 transition touch-manipulation pb-1">+</button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- 底部结算栏 -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 safe-area-pb z-20 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <div class="flex justify-between items-center max-w-2xl mx-auto">
                <div class="flex flex-col">
                    <div class="text-xs text-gray-500">预计支付 (CNY)</div>
                    <div class="text-2xl font-bold text-rose-600 leading-none">¥<span id="totalPrice">0</span></div>
                </div>

                @if($group->status === 'locked')
                    <button type="button" disabled class="bg-gray-400 text-white px-8 py-3 rounded-full font-bold opacity-80 cursor-not-allowed">
                        车队已锁定
                    </button>
                @elseif($isMember)
                    <button type="button" disabled class="bg-green-500 text-white px-8 py-3 rounded-full font-bold opacity-90 cursor-not-allowed flex items-center gap-2">
                        <span>✅</span> 您已上车
                    </button>
                @else
                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-rose-200 transition transform active:scale-95">
                        立即上车
                    </button>
                @endif
            </div>
        </div>
    </form>

    <script>
        // 将 PHP 数组传给 JS
        const products = @json($products);

        function updateQty(pid, change, limit = 999) {
            const input = document.getElementById(`qty-${pid}`);
            let val = parseInt(input.value) + change;
            
            // 基础校验
            if (val < 0) val = 0;
            
            // 限购校验
            if (val > limit) {
                // 简单的震动反馈 (如果设备支持)
                if (navigator.vibrate) navigator.vibrate(50);
                alert(`该商品每人限购 ${limit} 件`);
                val = limit;
            }
            
            input.value = val;
            calcTotal();
        }

        function calcTotal() {
            let total = 0;
            products.forEach(p => {
                const qtyInput = document.getElementById(`qty-${p.id}`);
                // 确保元素存在
                if (qtyInput) {
                    const qty = parseInt(qtyInput.value) || 0;
                    // 使用人民币价格 (price) 进行结算
                    total += qty * parseFloat(p.price);
                }
            });
            
            // 格式化金额：保留小数或整数
            const formattedTotal = Number.isInteger(total) ? total : total.toFixed(2);
            document.getElementById('totalPrice').innerText = formattedTotal;
        }
    </script>
</body>
</html>