import puppeteer from 'puppeteer';

// 获取参数
const url = process.argv[2]; 
if (!url) { 
    console.error("No URL provided"); 
    process.exit(1); 
}

try {
    // 启动浏览器
    const browser = await puppeteer.launch({
        headless: "new",
        args: [
            '--no-sandbox', 
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage', // 防止内存溢出
            '--disable-gpu'
        ]
    });

    const page = await browser.newPage();

    // 伪装 User-Agent
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    // 设置超时 (90秒)
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 90000 });

    // 提取内容
    const content = await page.evaluate(() => document.body.innerText);
    
    // 输出结果 (这就是 PHP 要拿到的 JSON)
    console.log(content);

    await browser.close();

} catch (error) {
    // 输出错误信息
    console.error("Browser Error:", error.message);
    process.exit(1);
}