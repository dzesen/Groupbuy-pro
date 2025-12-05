<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>上车失败</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 flex items-center justify-center h-screen p-4">
        <div class="bg-white p-8 rounded-2xl shadow-lg text-center max-w-sm w-full">
            <!-- 红色叉号图标 -->
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="text-4xl">😭</span>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-800 mb-2">上车失败</h1>
            
            <!-- 显示具体的错误原因 -->
            <p class="text-red-500 mb-6 font-medium">
                {{ session('error') ?? '未知错误，请稍后重试' }}
            </p>
            
            <div class="space-y-3">
                <button onclick="history.back()" class="block w-full bg-gray-200 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-300 transition">
                    返回重新选择
                </button>
                <a href="/" class="block w-full text-gray-400 text-sm hover:text-gray-600">
                    回首页
                </a>
            </div>
        </div>
    </body>
    </html>