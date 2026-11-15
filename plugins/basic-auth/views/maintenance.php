<?php 
// Send proper HTTP header
http_response_code(503);
header('Retry-After: 3600'); // 1 hour
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance in Progress | Mobile Banking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @keyframes gear-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .gear-animation {
            animation: gear-spin 4s linear infinite;
        }
        .progress-bar {
            background: linear-gradient(90deg, #e0e7ff 0%, #818cf8 50%, #e0e7ff 100%);
            background-size: 200% auto;
            animation: progress-animation 2s linear infinite;
        }
        @keyframes progress-animation {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<div  id="theme-main" class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full mx-4 bg-white rounded-2xl shadow-xl p-8 text-center">
        <!-- Status Badge -->
        <div class="mb-6 inline-flex items-center bg-yellow-100 text-yellow-800 px-4 py-2 rounded-full">
            <svg class="w-5 h-5 mr-2 gear-animation" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
            </svg>
            <span class="font-semibold">Under Maintenance</span>
        </div>

        <div class="space-y-6">
            <!-- Main Content -->
            <div class="space-y-4">
                <h1 class="text-3xl font-bold text-gray-900">Scheduled Maintenance in Progress</h1>
                <p class="text-gray-600 text-lg">
                    We're currently enhancing your banking experience. Our services will be back shortly 
                    with improved features and security updates.
                </p>
            </div>

            <!-- Progress & Timeline -->
            <div class="bg-gray-50 rounded-xl p-6 text-left space-y-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm font-medium">
                        <span class="text-yellow-600">Progress</span>
                        <span id="countdown" class="text-yellow-600">Calculating remaining time...</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div id="dynamic-progress" class="progress-bar h-full w-0 transition-all duration-1000"></div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Start Time</span>
                        <span class="text-gray-900 font-medium">06/MAR/2025, 12:00 AM GMT</span>
                    </div>
                    <div class="flex justify-between text-sm mt-2">
                        <span class="text-gray-600">Expected Completion</span>
                        <span class="text-gray-900 font-medium">08/MAR/2025, 4:00 AM GMT</span>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="space-y-3">
                <p class="text-gray-600">
                    Need immediate assistance? Contact our 24/7 support team:
                </p>
                <div class="flex justify-center space-x-4">
                    <a href="tel:+233548214842" class="text-yellow-600 hover:text-yellow-800 flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        +233 (0) 548-214-842
                    </a>
                    <a href="mailto:samabraham446@gmail.com" class="text-yellow-600 hover:text-yellow-800 flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        samabraham446@gmail.com
                    </a>
                </div>
            </div>
        </div>

        <!-- Social Media Footer -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="flex justify-center space-x-6">
                <a href="https://twitter.com/@AbrahamSam3" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-yellow-500 transition-colors">
                    <span class="sr-only">Twitter</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/>
                    </svg>
                </a>

                <!-- WhatsApp -->
                <a href="https://wa.me/233548214842" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-green-500 transition-colors">
                    <span class="sr-only">WhatsApp</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </a>
            </div>
            <p class="mt-4 text-center text-sm text-gray-500">
                &copy; 2025 tormorgroup. All rights reserved.
            </p>
        </div>
    </div>

<script>
    // Enhanced Countdown Timer with Seconds
    const expectedTime = new Date('2025-03-08T04:00:00').getTime();
    let progress = 0;
    const maxProgress = 90;

    function updateDisplay() {
        const now = new Date().getTime();
        const distance = expectedTime - now;
        const totalDuration = expectedTime - (new Date('2025-03-06T12:00:00').getTime());

        // Update progress bar
        progress = Math.min(maxProgress, ((now - new Date('2025-03-06T12:00:00').getTime()) / totalDuration) * 100);
        document.getElementById('dynamic-progress').style.width = `${progress}%`;

        // Update countdown timer
        if (distance < 0) {
            document.getElementById('countdown').innerHTML = "Maintenance completed!";
            document.getElementById('dynamic-progress').style.width = '100%';
            return;
        }

        const hours = Math.floor(distance / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        document.getElementById('countdown').innerHTML = 
            `Estimated time remaining: ${hours}h ${minutes}m ${seconds}s`;
    }

    // Update every second
    setInterval(updateDisplay, 1000);
    updateDisplay();

    // Simulate progress updates
    setInterval(() => {
        if (progress < maxProgress) {
            progress += Math.random() * 0.2;
            document.getElementById('dynamic-progress').style.width = `${progress}%`;
        }
    }, 1000);
</script>
</div>
</html>