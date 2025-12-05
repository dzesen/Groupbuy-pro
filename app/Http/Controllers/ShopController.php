<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ShopController extends Controller
{
    /**
     * 显示车队拼团页面
     */
    public function show(Group $group)
    {
        // 1. 加载关联数据
        $group->load(['campaign.products', 'members.items']);

        // 2. 计算进度
        $progress = $group->calculateProgress();

        // 3. 检查当前用户是否已在车里
        $currentMember = null;
        if (Auth::check()) {
            $currentMember = $group->members()->where('user_id', Auth::id())->first();
        }

        return view('shop.show', [
            'group' => $group,
            'products' => $group->campaign->products ?? collect(),
            'progress' => $progress,
            'isMember' => $currentMember,
            'currentUser' => Auth::user(),
        ]);
    }

    /**
     * 处理加入请求
     */
    public function join(Request $request, Group $group)
    {
        $validated = $request->validate([
            'quantities' => 'required|array',
            // ⭐⭐⭐ 之前这里写成了 min(0)，必须改为 min:0 ⭐⭐⭐
            'quantities.*' => 'integer|min:0',
        ]);

        $productsToBuy = [];
        foreach ($validated['quantities'] as $productId => $qty) {
            if ($qty > 0) {
                $productsToBuy[] = [
                    'product_id' => $productId,
                    'quantity' => $qty
                ];
            }
        }

        if (empty($productsToBuy)) {
            return back()->with('error', '请至少选择一件商品！');
        }

        try {
            $group->attemptJoin(Auth::user(), $productsToBuy);
            return redirect()->route('shop.success');

        } catch (Exception $e) {
            // ❌ 删除这一行：
        // return back()->with('error', $e->getMessage());
        
        // ✅ 替换为这一行：跳转到失败页，并带上错误信息
            return redirect()->route('shop.failed')->with('error', $e->getMessage());
        }
    }
}