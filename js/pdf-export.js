const generatePdfBtn =
document.getElementById("generatePdfBtn");

generatePdfBtn.addEventListener("click", async () => {

    /*
     * Prevent double click
     */

    if (generatePdfBtn.disabled) return;

    generatePdfBtn.disabled = true;

    const originalText =
    generatePdfBtn.innerText;

    generatePdfBtn.innerText =
    "Generating PDF...";

    try {

        const format =
        document.getElementById("pdfFormat").value;

        const activeTab =
        document.querySelector(".tab.active").dataset.tab;

        let platforms = [];

        if (
            format === "all" ||
            format === "all-dashboard" ||
            format === "all-insights"
            ) {

            platforms = [
                "facebook",
                "instagram",
                "twitter",
                "linkedin",
                "tiktok",
                "youtube"
            ];

        } else {

            platforms = [activeTab];

        }

        const payload = {

            project: CURRENT_PROJECT,

            format,

            company:
            document.getElementById("companyName").innerText,

            dateRange: {

                from: CURRENT_FROM,
                to: CURRENT_TO

            },

            platforms: {}

        };

        for (const platform of platforms) {

            const tabBtn =
            document.querySelector(
        `.tab[data-tab="${platform}"]`
        );

            if (tabBtn) {
                tabBtn.click();
            }

            await new Promise(resolve =>
                setTimeout(resolve, 500)
                );

            payload.platforms[platform] = {

                dashboard:
                captureDashboard(platform),

                charts:
                captureCharts(platform),

                insightsHTML: {

                    all:
                    await buildModalClone(platform, "all"),

                    posts:
                    await buildModalClone(platform, "posts"),

                    stories:
                    await buildModalClone(platform, "stories"),

                    reels:
                    await buildModalClone(platform, "reels")

                }
            };
        }

        const res = await fetch(
            "functions/generate-pdf.php",
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify(payload)
            }
            );

        /*
         * Check fetch response
         */

        if (!res.ok) {

            throw new Error(
        `PDF generation failed: ${res.status}`
        );

        }

        const blob = await res.blob();

        /*
         * Extra validation
         */

        if (blob.type !== "application/pdf") {

            throw new Error(
                "Invalid PDF response"
                );

        }

        const url =
        window.URL.createObjectURL(blob);

        const a =
        document.createElement("a");

        let filename = "";

        if (
            format === "all" ||
            format === "all-dashboard" ||
            format === "all-insights"
            ) {

            filename =
    `${CURRENT_PROJECT}-${format}.pdf`;

} else {

    filename =
`${CURRENT_PROJECT}-${activeTab}-${format}.pdf`;

}

a.href = url;
a.download = filename;

document.body.appendChild(a);

a.click();

a.remove();

window.URL.revokeObjectURL(url);

} catch (err) {

    console.error(err);

    alert(
        "Failed to generate PDF."
        );

} finally {

        /*
         * Re-enable button
         */

    generatePdfBtn.disabled = false;

    generatePdfBtn.innerText =
    originalText;

}

});

function removeEmojis(text = '') {

    return text.replace(
        /[\p{Emoji_Presentation}\p{Extended_Pictographic}]/gu,
        ''
        ).trim();
}


function captureDashboard(platform) {

	const prefixes = {
        facebook: "fb",
        instagram: "ig",
        twitter: "tw",
        linkedin: "li",
        tiktok: "tt",
        youtube: "yt"
    };

    const prefix = prefixes[platform];

    return {

        current: {
            reach: document.getElementById(`${prefix}-reach`)?.dataset.value || 0,
            engagements: document.getElementById(`${prefix}-engagements`)?.dataset.value || 0,
            followers: document.getElementById(`${prefix}-followers`)?.dataset.value || 0,
            visits: document.getElementById(`${prefix}-visits`)?.dataset.value || 0,
            pageReach: document.getElementById(`${prefix}-page-reach`)?.dataset.value || 0
        },

        previous: {
            reach: document.getElementById(`${prefix}-prev-reach`)?.innerText || '—',
            engagements: document.getElementById(`${prefix}-prev-engagements`)?.innerText || '—',
            followers: document.getElementById(`${prefix}-prev-followers`)?.innerText || '—',
            visits: document.getElementById(`${prefix}-prev-visits`)?.innerText || '—',
            pageReach: document.getElementById(`${prefix}-prev-page-reach`)?.innerText || '—'
        },

        overtime: {
            reach: document.getElementById(`${prefix}-year-reach`)?.innerText || 0,
            engagements: document.getElementById(`${prefix}-year-engagements`)?.innerText || 0,
            followers: document.getElementById(`${prefix}-year-followers`)?.innerText || 0,
            visits: document.getElementById(`${prefix}-year-visits`)?.innerText || 0,
            pageReach: document.getElementById(`${prefix}-year-page-reach`)?.innerText || 0
        }
    };
}

function captureCharts(platform) {

    const chartIds = {
        facebook: [
            "fbViewsChart",
            "fbFollowsChart",
            "fbVisitsChart",
            "fbInteractionsChart",
            "fbPageReachChart"
        ],

        instagram: [
            "igViewsChart",
            "igFollowsChart",
            "igVisitsChart",
            "igInteractionsChart",
            "igPageReachChart"
        ],

        twitter: [
            "twViewsChart",
            "twFollowsChart",
            "twVisitsChart",
            "twInteractionsChart",
            "twPageReachChart"
        ],

        linkedin: [
            "liViewsChart",
            "liFollowsChart",
            "liVisitsChart",
            "liInteractionsChart",
            "liPageReachChart"
        ],

        tiktok: [
            "ttViewsChart",
            "ttFollowsChart",
            "ttVisitsChart",
            "ttInteractionsChart",
            "ttPageReachChart"
        ],

        youtube: [
            "ytViewsChart",
            "ytFollowsChart",
            "ytVisitsChart",
            "ytInteractionsChart",
            "ytPageReachChart"
        ]
    };

    const ids = chartIds[platform] || [];

    const charts = {};

    ids.forEach(id => {

        const canvas = document.getElementById(id);

        if (!canvas) {
            console.warn("Canvas not found:", id);
            return;
        }

        /*
         * Force Chart.js redraw
         */

        const chartInstance = Chart.getChart(canvas);

        if (chartInstance) {
            chartInstance.resize();
            chartInstance.update('none');
        }

        /*
         * Hidden tab fix
         */

        if (canvas.width === 0 || canvas.height === 0) {

            console.warn("Canvas hidden or empty:", id);

            return;
        }

        try {

            const tempCanvas =
            document.createElement("canvas");

            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;

            const ctx =
            tempCanvas.getContext("2d");

            /*
             * White background
             */

            ctx.fillStyle = "#ffffff";
            ctx.fillRect(
                0,
                0,
                tempCanvas.width,
                tempCanvas.height
                );

            ctx.drawImage(canvas, 0, 0);

            charts[id] =
            tempCanvas.toDataURL(
                "image/jpeg",
                0.95
                );

        } catch (e) {

            console.error(
                "Chart export failed:",
                id,
                e
                );
        }
    });

    return charts;
}


function normalizePostType(type = "") {

    const lower = type.toLowerCase();

    if (/post|photo|album|carousel/.test(lower)) {
        return "posts";
    }

    if (/story/.test(lower)) {
        return "stories";
    }

    if (/reel|video/.test(lower)) {
        return "reels";
    }

    return "other";
}

function generateInsightsChartImage(platform, type = "all") {

    const rawData = weeklyCache[platform] || [];

    if (!rawData.length) return "";

    const labels = [];
    const values = [];

    rawData.forEach((weekData, index) => {

        const week = getLast12Weeks()[index];

        const start = week.value.split('|')[0];

        labels.push(
            new Date(start).toLocaleDateString('en-US',{
                month:'short',
                day:'2-digit'
            })
            );

        let views = 0;

        if(type === "all"){

            views = Number(weekData.reach) || 0;

        } else {

            const breakdown =
            weekData.content_breakdown || {};

            for(const key in breakdown){

                const normalizedType =
                normalizePostType(key);

                if(normalizedType !== type) continue;

                views +=
                Number(breakdown[key].views) || 0;
            }
        }

        values.push(views);

    });

    const canvas = document.createElement("canvas");

    canvas.width = 1200;
    canvas.height = 500;

    const chart = new Chart(canvas, {

        type: 'line',

        data: {

            labels,

            datasets: [{
                label: 'Views',
                data: values
            }]
        },

        options: {
            responsive: false,
            animation: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    chart.update();

    return canvas.toDataURL("image/png");
}

function filterBreakdownByType(breakdown, type) {

    const filtered = {};

    for (const key in breakdown) {

        const lower = key.toLowerCase();

        if (
            type === "posts" &&
            /post|photo|album|carousel/.test(lower)
            ) {
            filtered[key] = breakdown[key];
    }

    if (
        type === "stories" &&
        lower.includes("stories")
        ) {
        filtered[key] = breakdown[key];
}

if (
    type === "reels" &&
    /reels|videos/.test(lower)
    ) {
    filtered[key] = breakdown[key];
}
}

return filtered;
}


async function imageUrlToBase64(url) {

    if (!url) return '';

    try {

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const blob = await response.blob();

        // Prevent invalid blobs
        if (!blob || blob.size === 0) {
            return '';
        }

        return await new Promise((resolve) => {

            const reader = new FileReader();

            reader.onloadend = () => {

                if (
                    typeof reader.result !== 'string' ||
                    !reader.result.startsWith('data:image')
                    ) {
                    resolve('');
                return;
            }

            resolve(reader.result);
        };

        reader.readAsDataURL(blob);

    });

    } catch (err) {

        console.warn("Image convert failed:", url, err);

        return '';
    }
}

async function buildModalClone(platform, type = "all") {

    const rawData = weeklyCache[platform] || [];

    if (!rawData.length) {
        return `
            <p>No insights data available.</p>
        `;
    }

    const data = aggregateModalData(rawData);

    const fallbackThumb = await imageUrlToBase64(
        window.location.origin + '/social-dashboard/img/placeholder.png'
        );

    const filteredBreakdown =
    type === "all"
    ? data.content_breakdown
    : filterBreakdownByType(
        data.content_breakdown,
        type
        );


    const filteredPosts =
    (data.top_posts || []).filter(post => {

        if(type === "all") return true;

        const normalized =
        normalizePostType(post.type || "");

        return normalized === type;

    });

    const allChart =
    generateInsightsChartImage(platform, "all");

    const postsChart =
    generateInsightsChartImage(platform, "posts");

    const storiesChart =
    generateInsightsChartImage(platform, "stories");

    const reelsChart =
    generateInsightsChartImage(platform, "reels");


    let overviewViews = 0;
    let overviewEngagements = 0;

    if (type === "all") {

        overviewViews = Number(data.reach) || 0;
        overviewEngagements = Number(data.engagements) || 0;

    } else {

        Object.keys(filteredBreakdown || {}).forEach(key => {

            const row = filteredBreakdown[key];

            overviewViews += Number(row.views) || 0;
            overviewEngagements += Number(row.engagements) || 0;

        });

    }

    
    let html = "";

    const hasBreakdown = Object.keys(filteredBreakdown || {}).length > 0;
    const hasTopContent = (filteredPosts || []).length > 0;

    let breakdownHTML = "";
    let topContentHTML = "";

/* =========================
   CONTENT BREAKDOWN
========================= */
    if (hasBreakdown) {
        breakdownHTML += `
        <div class="section-title">
            CONTENT BREAKDOWN
        </div>

        <table border="1" cellspacing="0" cellpadding="6" width="100%">
            <thead>
                <tr>
                    <th style="font-size:12px">Type</th>
                    <th style="font-size:12px">Posts</th>
                    <th style="font-size:12px">Eng.</th>
                    <th style="font-size:12px">Views</th>
                </tr>
            </thead>
            <tbody>
        `;

        Object.keys(filteredBreakdown || {}).forEach(key => {
            const row = filteredBreakdown[key];

            breakdownHTML += `
            <tr>
                <td style="font-size:10px">${key}</td>
                <td style="font-size:10px">${row.posts || 0}</td>
                <td style="font-size:10px">${row.engagements || 0}</td>
                <td style="font-size:10px">${row.views || 0}</td>
            </tr>
            `;
        });

        breakdownHTML += `
            </tbody>
        </table>
        `;
    }

/* =========================
   TOP CONTENT
========================= */
    if (hasTopContent) {
        topContentHTML += `
        <div class="section-title">
            TOP CONTENT
        </div>

        <table width="100%">
            <tr>
        `;

        for (const [index, post] of filteredPosts.entries()) {
            let embeddedThumb = await imageUrlToBase64(post.thumbnail);

            if (!embeddedThumb) {
                embeddedThumb = fallbackThumb;
            }

            if (index % 3 === 0 && index !== 0) {
                topContentHTML += `</tr><tr>`;
            }

            topContentHTML += `
            <td width="33%" style="padding:5px; vertical-align:top;">
                <div class="top-content-card"
                 style="border:1px solid #ccc; padding:5px; border-radius:6px;">

                    <img src="${embeddedThumb}"
                        style="width:100%; height:100px; object-fit:cover; margin-bottom:5px;">

                    <div style="font-size:10px; font-weight:bold; margin-bottom:5px; min-height:30px;">
                        ${removeEmojis(post.message || 'No caption')}
                    </div>

                    <div style="font-size:11px;">
                        Views: ${(post.views || 0).toLocaleString()}
                    </div>

                    <div style="font-size:11px;">
                        Engagements: ${(post.engagements || 0).toLocaleString()}
                    </div>

                </div>
            </td>
            `;
        }

        topContentHTML += `
            </tr>
        </table>
        `;
    }

/* =========================
   FINAL HTML
========================= */
    html += `
<div class="insights-wrapper">

    <div class="insights-left">

            <div class="section-title">
                OVERVIEW
            </div>

            <table class="stats-grid">
                <tr>
                    <td class="stat-box">
                        <div class="stat-label">Views</div>
                        <div class="stat-value">
                            ${overviewViews.toLocaleString()}
                        </div>
                    </td>

                    <td class="stat-box">
                        <div class="stat-label">Engagements</div>
                        <div class="stat-value">
                            ${overviewEngagements.toLocaleString()}
                        </div>
                    </td>
                </tr>
            </table>

            ${breakdownHTML}
            ${topContentHTML}

           </div>

    <div class="insights-right">

            <div class="section-title">
                ${
                    type === "all"
                    ? "PERFORMANCE TREND"
                    : type.toUpperCase() + " TREND"
                }
            </div>

            <div class="chart-box-insight">
                <img
                    src="${
                        type === "all"
                        ? allChart
                        : type === "posts"
                        ? postsChart
                        : type === "stories"
                        ? storiesChart
                        : reelsChart
                        }"
                    class="chart-img-insight"
                >
            </div>

           </div>

</div>
                    `;

                    return html;
                }