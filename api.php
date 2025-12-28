<?php header("Content-type: application/javascript"); ?>
<?php
// Calculate the root URL of the application
$scriptDir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // Go up from assets/js/
$baseUrl = rtrim($scriptDir, '/\\');
// If on root, baseUrl might be empty string, which is fine as /api works
// If on /subdir, baseUrl is /subdir
if ($baseUrl === '') $baseUrl = ''; 
?>
const API_BASE = "<?php echo $baseUrl; ?>/api";

async function apiRequest(endpoint, method = "GET", body = null) {
    const headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    };

    // Attach token from localStorage if available (fallback for when cookies fail)
    const token = localStorage.getItem("auth_token");
    if (token) {
        headers["Authorization"] = `Bearer ${token}`;
    }

    const config = {
        method,
        headers,
    };

    if (body) {
        config.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, config);
        
        let data;
        try {
            const text = await response.text();
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Failed to parse JSON response:", text);
                data = null;
            }
        } catch (e) {
            console.error("Failed to read response text", e);
            data = null; 
        }
        
        if (!response.ok) {
            // Handle 401 Unauthorized globally
            if (response.status === 401) {
                // If we are not already on the login page, redirect
                if (!window.location.pathname.includes("login.php")) {
                    console.warn("Unauthorized access, redirecting to login...");
                    window.location.href = "login.php";
                    throw new Error("Unauthorized");
                }
            }
            
            const errorMessage = (data && (data.message || data.error)) 
                ? (data.message || data.error) 
                : `Request failed with status ${response.status}`;
                
            throw new Error(errorMessage);
        }
        
        return data;
    } catch (error) {
        console.error("API Error:", error);
        throw error;
    }
}

// Helper to show messages in forms
function showMessage(elementId, message, type = "error") {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    el.textContent = message;
    el.className = `message ${type}`;
    el.style.display = "block";
    
    // Auto hide after 5 seconds if success
    if (type === "success") {
        setTimeout(() => {
            el.style.display = "none";
        }, 5000);
    }
}
