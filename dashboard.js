 /* =========================================
     GLOBALS
  ========================================= */

let fbQuarterCharts = {};
let igQuarterCharts = {};
let twQuarterCharts = {};
let liQuarterCharts = {};
let comparisonChartInstance = null;
let modalDataCache = null;
const CHART_API = "functions/api.php";
let CURRENT_FROM = '';
let CURRENT_TO = '';
  // Remove this line if API_URL already exists globally
  // const API_URL = "functions/api.php";



let weeklyCache = {
    facebook: [],
    instagram: [],
    twitter: [],
    linkedin: []
};


function formatValue(value, options = {}) {

    const { isFollower = false } = options;

    // null / undefined → "-"
    if (value === null || value === undefined) return "-";

    // Ensure number
    const num = Number(value);

    // Followers special rule
    if (isFollower) {
        if (num === 0) return "0"; // ❗ no plus
        return `+${num}`;
    }

    // Normal numbers
    return num.toLocaleString();
}


  /* =========================================
     TAB SWITCHING
  ========================================= */

document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

      tab.classList.add('active');
      document.getElementById(tab.dataset.tab).classList.add('active');

       // 👇 ADD THIS
      updateReportMeta(tab.dataset.tab);   
  });
});


document.addEventListener('DOMContentLoaded', () => {

    const fromInput = document.getElementById('fromDate');
    const toInput = document.getElementById('toDate');

    // Default = last 30 days
    const today = new Date();

    const past = new Date();
    past.setDate(today.getDate() - 30);

    const format = (d) => d.toISOString().split('T')[0];

    fromInput.value = format(past);
    toInput.value = format(today);

    CURRENT_FROM = fromInput.value;
    CURRENT_TO = toInput.value;

    document.getElementById('applyRange').addEventListener('click', () => {

        CURRENT_FROM = fromInput.value;
        CURRENT_TO = toInput.value;

        loadAllData();
    });

    const projectPicker = document.getElementById('projectPicker');

    if (projectPicker) {

        projectPicker.value = CURRENT_PROJECT;

        projectPicker.addEventListener('change', () => {

            const selectedProject = projectPicker.value;

            const url = new URL(window.location.href);

            url.searchParams.set('project', selectedProject);

            window.location.href = url.toString();

        });

    }

    loadAllData();

});


/* =========================================
   HELPER: Previous Week & % Difference
========================================= */

function getPreviousDateRange(from, to) {

    const startDate = new Date(from);
    const endDate = new Date(to);

    const diffDays = Math.ceil(
        (endDate - startDate) / (1000 * 60 * 60 * 24)
        ) + 1;

    const prevEnd = new Date(startDate);
    prevEnd.setDate(prevEnd.getDate() - 1);

    const prevStart = new Date(prevEnd);
    prevStart.setDate(prevStart.getDate() - (diffDays - 1));

    const format = d => d.toISOString().split('T')[0];

    return {
        start: format(prevStart),
        end: format(prevEnd)
    };
}

function getPrevWeekRange(currentWeek) {
    const [start, end] = currentWeek.split('|');
    const startDate = new Date(start);
    const endDate = new Date(end);
    const prevStart = new Date(startDate);
    prevStart.setDate(prevStart.getDate() - 7);
    const prevEnd = new Date(endDate);
    prevEnd.setDate(prevEnd.getDate() - 7);
    return [prevStart.toISOString().split('T')[0], prevEnd.toISOString().split('T')[0]].join('|');
}

function getWeeklyDifference(current, previous) {
    if (!previous) return '';

    const diff = ((current - previous) / previous) * 100;
    const isUp = diff >= 0;

    const arrow = isUp ? "&#9650;" : "&#9660;";
    const color = isUp ? "green" : "red";

    return `
        <div style="font-size:12px; color:${color}; line-height:1.2;">
            ${arrow} ${Math.abs(diff).toFixed(1)}%
        </div>
    `;
}

function showLoader() {
    document.getElementById('globalLoader').style.display = 'flex';
}

function hideLoader() {
    document.getElementById('globalLoader').style.display = 'none';
}


function updateCompanyName(name) {
    const el = document.getElementById('companyName');
    el.textContent = name || 'No Company Name';
}
/* =========================================
   LOAD FACEBOOK DATA
========================================= */

async function loadFacebookData() {
    const start = CURRENT_FROM;
    const end = CURRENT_TO;
    const previous = getPreviousDateRange(start, end);

    const prevStart = previous.start;
    const prevEnd = previous.end;

    const [res, lastRes] = await Promise.all([
        fetch(`${API_URL}?platform=facebook&project=${CURRENT_PROJECT}&start=${start}&end=${end}`),
        fetch(`${API_URL}?platform=facebook&project=${CURRENT_PROJECT}&start=${prevStart}&end=${prevEnd}`)
    ]);

    const data = await res.json();
    const lastWeekData = await lastRes.json();

    if (data.error && data.error !== '') {
        console.warn("API Notice:", data.error);
        return;
    }

    document.getElementById('fb-page-reach').dataset.value = data.page_reach || 0;
    document.getElementById('fb-reach').dataset.value = data.reach || 0;
    document.getElementById('fb-engagements').dataset.value = data.engagements || 0;
    document.getElementById('fb-followers').dataset.value = data.new_followers || 0;
    document.getElementById('fb-visits').dataset.value = data.visits || 0;

    document.getElementById('fb-page-reach').innerHTML =
    formatValue(data.page_reach || 0) +
    getWeeklyDifference(
        data.page_reach || 0,
        lastWeekData.page_reach || 0
        );

    // Stats with percentage difference
    document.getElementById('fb-reach').innerHTML =
    formatValue(data.reach) + getWeeklyDifference(data.reach, lastWeekData.reach);

    document.getElementById('fb-engagements').innerHTML =
    formatValue(data.engagements) + getWeeklyDifference(data.engagements, lastWeekData.engagements);

    document.getElementById('fb-followers').innerHTML =
    formatValue(data.new_followers, { isFollower: true }) +
    getWeeklyDifference(data.new_followers, lastWeekData.new_followers);

    document.getElementById('fb-visits').innerHTML =
    formatValue(data.visits) + getWeeklyDifference(data.visits, lastWeekData.visits);

    document.getElementById('fb-prev-page-reach').innerHTML =
    formatValue(lastWeekData.page_reach || 0);

    
    const tbody = document.getElementById('fb-breakdown');
    tbody.innerHTML = '';
    const breakdown = data.content_breakdown || {};

    if(Object.keys(breakdown).length === 0){

        tbody.innerHTML = `
        <tr>
            <td colspan="4" style="text-align:center">
                No posts during this week
            </td>
        </tr>
        `;

    }else{

        for (const type in breakdown) {

            const row = breakdown[type];

            tbody.innerHTML += `
        <tr>
            <td>${type}</td>
            <td>${row.posts}</td>
            <td>${row.engagements}</td>
            <td>${row.views || 0}</td>
        </tr>
            `;
        }

    }

    // Previous Performance
    document.getElementById('fb-prev-reach').innerHTML = formatValue(lastWeekData.reach);
    document.getElementById('fb-prev-engagements').innerHTML = formatValue(lastWeekData.engagements);
    document.getElementById('fb-prev-followers').innerHTML = formatValue(lastWeekData.new_followers, { isFollower: true });
    document.getElementById('fb-prev-visits').innerHTML =formatValue(lastWeekData.visits);



    if (!data || Object.keys(data).length === 0) {
        console.warn("No stored data yet for this week");
        return;
    }


    return data;
}

/* =========================================
   LOAD INSTAGRAM DATA
========================================= */

async function loadInstagramData() {
    const start = CURRENT_FROM;
    const end = CURRENT_TO;
    const previous = getPreviousDateRange(start, end);

    const prevStart = previous.start;
    const prevEnd = previous.end;

    const [res, lastRes] = await Promise.all([
        fetch(`${API_URL}?platform=instagram&project=${CURRENT_PROJECT}&start=${start}&end=${end}`),
        fetch(`${API_URL}?platform=instagram&project=${CURRENT_PROJECT}&start=${prevStart}&end=${prevEnd}`)
    ]);

    const data = await res.json();
    const lastWeekData = await lastRes.json();

    if (data.error && data.error !== '') {
        console.warn("API Notice:", data.error);
        return;
    }

    document.getElementById('ig-page-reach').dataset.value = data.page_reach || 0;
    document.getElementById('ig-reach').dataset.value = data.reach || 0;
    document.getElementById('ig-engagements').dataset.value = data.engagements || 0;
    document.getElementById('ig-followers').dataset.value = data.new_followers || 0;
    document.getElementById('ig-visits').dataset.value = data.visits || 0;

    document.getElementById('ig-page-reach').innerHTML =
    formatValue(data.page_reach || 0) +
    getWeeklyDifference(
        data.page_reach || 0,
        lastWeekData.page_reach || 0
        );

    document.getElementById('ig-reach').innerHTML =
    formatValue(data.reach) + getWeeklyDifference(data.reach, lastWeekData.reach);

    document.getElementById('ig-engagements').innerHTML =
    formatValue(data.engagements) + getWeeklyDifference(data.engagements, lastWeekData.engagements);

    document.getElementById('ig-followers').innerHTML =
    formatValue(data.new_followers || 0, { isFollower: true }) +
    getWeeklyDifference(data.new_followers, lastWeekData.new_followers);

    document.getElementById('ig-visits').innerHTML =
    formatValue(data.visits) +
    getWeeklyDifference(data.visits, lastWeekData.visits);

    
    document.getElementById('ig-prev-page-reach').innerHTML =
    formatValue(lastWeekData.page_reach || 0);

    const tbody = document.getElementById('ig-breakdown');
    tbody.innerHTML = '';

    const breakdown = data.content_breakdown || {};

    if(Object.keys(breakdown).length === 0){

        tbody.innerHTML = `
    <tr>
        <td colspan="4" style="text-align:center">
            No posts during this week
        </td>
        </tr>`;

    }else{

        for (const type in breakdown) {

            const row = breakdown[type];

            tbody.innerHTML += `
        <tr>
            <td>${type}</td>
            <td>${row.posts}</td>
            <td>${row.engagements}</td>
            <td>${row.views || 0}</td>
            </tr>`;
        }

    }

    document.getElementById('ig-prev-reach').innerHTML = formatValue(lastWeekData.reach);
    document.getElementById('ig-prev-engagements').innerHTML = formatValue(lastWeekData.engagements);
    document.getElementById('ig-prev-followers').innerHTML = formatValue(lastWeekData.new_followers, { isFollower: true });
    document.getElementById('ig-prev-visits').innerHTML =formatValue(lastWeekData.visits);

    if (!data || Object.keys(data).length === 0) {
        console.warn("No stored data yet for this week");
        return;
    }

    return data;
}

async function loadPlatformData(platform) {

    switch(platform){

    case "facebook":
        return await loadFacebookData();

    case "instagram":
        return await loadInstagramData();

    case "twitter":
    case "linkedin": {

        const res = await fetch(
    `${API_URL}?platform=${platform}&project=${CURRENT_PROJECT}&start=${CURRENT_FROM}&end=${CURRENT_TO}`
    );

        const data = await res.json();

        if (data.restriction) {
            console.log(`${platform}: ${data.restriction}`);
        }

        return data;
    }
}
}

/* =========================================
   HELPER
========================================= */

function getActivePlatform() {
    return document.querySelector(".tab.active").dataset.tab;
}

function getInsightsData(platform) {
    return {
        aggregated: modalDataCache?.aggregated || {
            reach: 0,
            engagements: 0,
            content_breakdown: {},
            top_posts: []
        }
    };
}

async function fetchYearlyData(platform) {
    const yearStart = "2026-01-01";
    const today = new Date().toISOString().split('T')[0];

    const res = await fetch(`${API_URL}?platform=${platform}&project=${CURRENT_PROJECT}&start=${yearStart}&end=${today}`);
    return await res.json();
}

function showModalLoader() {
    document.getElementById('modalLoader').style.display = 'flex';
}

function hideModalLoader() {
    document.getElementById('modalLoader').style.display = 'none';
}



/* =========================================
   COMPARISON CHART
========================================= */

async function loadComparisonChart(fbData, igData) {
    if (!fbData || !igData) return;

    if (comparisonChartInstance) comparisonChartInstance.destroy();
    comparisonChartInstance = new Chart(
        document.getElementById('comparisonChart'),
        {
            type: 'bar',
            data: {
                labels: ['Reach','Engagements','New Followers','Profile Visits'],
                datasets: [
                    {
                        label: 'Facebook',
                        data: [
                            fbData.reach || 0,
                            fbData.engagements || 0,
                            fbData.new_followers || 0,
                            fbData.visits || 0
                        ]
                    },
                    {
                        label: 'Instagram',
                        data: [
                            igData.reach || 0,
                            igData.engagements || 0,
                            igData.new_followers || 0,
                            igData.visits || 0
                        ]
                    }
                ]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        }
        );
}

async function loadQuarterlyCharts(platform) {

    const weeks = getLast12Weeks();

    const labels = [];
    const views = [];
    const follows = [];
    const visits = [];
    const interactions = [];
    const pageReach = [];

    const requests = weeks.map(week => {

        const [start,end] = week.value.split('|');

        return fetch(`${CHART_API}?platform=${platform}&project=${CURRENT_PROJECT}&start=${start}&end=${end}`)
        .then(r=>r.json())
        .then(data => ({
            week,
            data
        }));
    });

    const results = await Promise.all(requests);

    // 👇 ADD THIS
    weeklyCache[platform] = results.map(r => r.data);


    results.forEach(r=>{

        const start = r.week.value.split('|')[0];

        const label = new Date(start).toLocaleDateString('en-US',{
            month:'short',
            day:'2-digit'
        });

        labels.push(label);

        views.push(Number(r.data.reach) || 0);
        pageReach.push(Number(r.data.page_reach) || 0);

        follows.push(
            Number(r.data.new_followers) ||
            Number(r.data.followers) ||
            0
            );

        visits.push(Number(r.data.visits) || 0);

        interactions.push(
            Number(r.data.engagements) ||
            Number(r.data.engagement) ||
            Number(r.data.interactions) ||
            Number(r.data.total_interactions) ||
            0
            );

        

    });

    createQuarterChart(platform,'Views',labels,views);
    createQuarterChart(platform,'Follows',labels,follows);
    createQuarterChart(platform,'Visits',labels,visits);
    createQuarterChart(platform,'Interactions',labels,interactions);
    createQuarterChart(platform,'PageReach',labels,pageReach);


}


function getLast12Weeks(){

    const weeks = [];
    const today = new Date();

    const day = today.getDay();
    const sunday = new Date(today);
    sunday.setDate(today.getDate() - day);

    for(let i=11;i>=0;i--){

        const end = new Date(sunday);
        end.setDate(sunday.getDate() - (i*7));

        const start = new Date(end);
        start.setDate(end.getDate() - 6);

        weeks.push({
            value: `${start.toISOString().split('T')[0]}|${end.toISOString().split('T')[0]}`
        });

    }

    return weeks;

}


function aggregateModalData(weeksData) {

    const result = {
        reach: 0,
        engagements: 0,
        content_breakdown: {},
        top_posts: []
    };

    weeksData.forEach(data => {

        result.reach += Number(data.reach) || 0;
        result.engagements += Number(data.engagements) || 0;

        const breakdown = data.content_breakdown || {};

        for (const key in breakdown) {

            if (!result.content_breakdown[key]) {
                result.content_breakdown[key] = {
                    posts: 0,
                    engagements: 0,
                    views: 0
                };
            }

            result.content_breakdown[key].posts += breakdown[key].posts || 0;
            result.content_breakdown[key].engagements += breakdown[key].engagements || 0;
            result.content_breakdown[key].views += breakdown[key].views || 0;
        }

        result.top_posts.push(...(data.top_posts || []));
    });

   // Remove duplicates using permalink or id
    const uniquePosts = {};

    result.top_posts.forEach(post => {

        const key =
        post.id ||
        post.permalink ||
    `${post.message}-${post.created_time}`;

    // Keep higher-view version
    if (
        !uniquePosts[key] ||
        (post.views || 0) > (uniquePosts[key].views || 0)
        ) {
        uniquePosts[key] = post;
}

});

    result.top_posts = Object.values(uniquePosts)
    .sort((a, b) => (b.views || 0) - (a.views || 0))
    .slice(0, 10);

    return result;
}

function createQuarterChart(platform, metric, labels, data) {

    const prefixMap = {
        facebook: "fb",
        instagram: "ig",
        twitter: "tw",
        linkedin: "li"
    };

    const storageMap = {
        facebook: fbQuarterCharts,
        instagram: igQuarterCharts,
        twitter: twQuarterCharts,
        linkedin: liQuarterCharts
    };

    const chartId = `${prefixMap[platform]}${metric}Chart`;
    const storage = storageMap[platform];

    if (storage[metric]) storage[metric].destroy();

    storage[metric] = new Chart(
        document.getElementById(chartId),
        {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: metric,
                    data: data,

                     // 👇 ADD THESE
                    backgroundColor: '#f7931a',
                    borderColor: '#f7931a',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        }
        );
}



function getYearlyIGFollowers(monthlyData) {
    if (!monthlyData) return 0;

    let total = 0;

    for (const key in monthlyData) {
        total += Number(monthlyData[key]?.followers_month || 0);
    }

    return total;
}

async function fetchPlatformData(platform) {

    const response = await fetch(
`${CHART_API}?project=${CURRENT_PROJECT}&platform=${platform}&start=${CURRENT_FROM}&end=${CURRENT_TO}`
);

    return await response.json();

}


/* =========================================
   LOAD ALL DATA
========================================= */

async function loadAllData() {

    showLoader();

    try {

        const [
        fbData,
        igData,
        fbYear,
        igYear
    ] = await Promise.all([
        loadFacebookData(),
        loadInstagramData(),
        fetchYearlyData('facebook'),
        fetchYearlyData('instagram')
    ]);

    updateCompanyName(
        fbData?.company_name ||
        igData?.company_name
        );

    document.getElementById('fb-year-reach').innerHTML =
    formatValue(fbYear?.reach);

    document.getElementById('fb-year-engagements').innerHTML =
    formatValue(fbYear?.engagements);

    document.getElementById('fb-year-followers').innerHTML =
    formatValue(fbYear?.new_followers, { isFollower: true });

    document.getElementById('fb-year-visits').innerHTML =
    formatValue(fbYear?.visits);

    document.getElementById('fb-year-page-reach').innerHTML =
    formatValue(fbYear?.page_reach || 0);


    document.getElementById('ig-year-reach').innerHTML =
    formatValue(igYear?.reach);

    document.getElementById('ig-year-engagements').innerHTML =
    formatValue(igYear?.engagements);

    const igYearFollowers = getYearlyIGFollowers(igYear.instagram_monthly);

    document.getElementById('ig-year-followers').innerHTML =
    formatValue(igYearFollowers, { isFollower: true });

    document.getElementById('ig-year-visits').innerHTML =
    formatValue(igYear?.visits);

    document.getElementById('ig-year-page-reach').innerHTML =
    formatValue(igYear?.page_reach || 0);

    // loadComparisonChart(fbData, igData);

        /* WAIT FOR BOTH CHARTS */
    await Promise.all([
        loadQuarterlyCharts('facebook'),
        loadQuarterlyCharts('instagram'),
        loadQuarterlyCharts('twitter'),
        loadQuarterlyCharts('linkedin')
    ]);

} catch (err) {
    console.error("Dashboard load error:", err);
} finally {
    hideLoader();
}
}
/* =========================================
   INITIAL LOAD
========================================= */

// document.addEventListener('DOMContentLoaded', () => {
//     const projectPicker = document.getElementById('projectPicker');

//     // If dropdown exists → allow switching
//     if (projectPicker) {
//         projectPicker.value = CURRENT_PROJECT;

//         projectPicker.addEventListener('change', () => {
//             const selectedProject = projectPicker.value;

//             const url = new URL(window.location.href);
//             url.searchParams.set('project', selectedProject);

//     window.location.href = url.toString(); // 🔥 reload with new project
// });
//     }


//     loadAllData();
// });

async function openInsightsModal(platform) {

    showModalLoader(); // ✅ ADD HERE

    const modal = document.getElementById('insightsModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const start = CURRENT_FROM;
    const end = CURRENT_TO;

    const isFacebook = platform === 'facebook';

    const reach = isFacebook
    ? document.getElementById('fb-reach').innerHTML
    : document.getElementById('ig-reach').innerHTML;

    const engagements = isFacebook
    ? document.getElementById('fb-engagements').innerHTML
    : document.getElementById('ig-engagements').innerHTML;

    const followers = isFacebook
    ? document.getElementById('fb-followers').innerHTML
    : document.getElementById('ig-followers').innerHTML;

    title.innerText = isFacebook
    ? 'Facebook Content Overview'
    : 'Instagram Content Overview';

    content.innerHTML = `

        <!-- Tabs -->
        <div class="tabs modal-tabs">
            <div class="tab active" data-type="all">All</div>
            <div class="tab" data-type="posts">Posts</div>
            <div class="tab" data-type="stories">Stories</div>
            <div class="tab" data-type="reels">Reels</div>
        </div>

        <!-- Top Stats -->
       <div id="modalStats"></div>

        <!-- Chart Section -->
        <canvas id="modalChart"></canvas>

        <!-- Breakdown Section -->
        <h2>Views Breakdown</h2>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Posts</th>
                    <th>Engagements</th>
                    <th>Views</th>
                </tr>
            </thead>
            <tbody id="modalBreakdown"></tbody>
        </table>

        <!-- Top Content -->
        <h2>Top Content by Views</h2>
        <div id="modalTopContent"></div>
    `;

    modal.style.display = 'block';

    const endDate = new Date(end);
    const startDate = new Date(endDate);
    startDate.setDate(startDate.getDate() - 56); // 8 weeks

    const modalStart = startDate.toISOString().split("T")[0];
    const modalEnd = endDate.toISOString().split("T")[0];

    const weeks = getLast12Weeks();

    const requests = weeks.map(w => {

        const [start, end] = w.value.split('|');

        return fetch(
    `${API_URL}?platform=${platform}&project=${CURRENT_PROJECT}&start=${start}&end=${end}`
    ).then(r => r.json());

    });

    const results = await Promise.all(requests);

    modalDataCache = {
        raw: results,
        aggregated: aggregateModalData(results)
    };

    console.log("MODAL DATA:", modalDataCache);

    initModalTabs(platform);

    renderModalStats("all", modalDataCache.aggregated);
    loadModalChart(platform,"all");
    loadModalBreakdown(platform,"all");

    hideModalLoader(); // ✅ ADD HERE

}

function closeInsightsModal() {
    document.getElementById('insightsModal').style.display = 'none';
}

let modalChartInstance = null;

function loadModalChart(platform, type="all") {

    const labels = [];
    const values = [];

    modalDataCache.raw.forEach((weekData, index) => {

        const week = getLast12Weeks()[index];
        const start = week.value.split('|')[0];

        labels.push(new Date(start).toLocaleDateString('en-US',{
            month:'short',
            day:'2-digit'
        }));

        let views = 0;

        if(type === "all"){
            views = weekData.reach || 0;
        } else {
            const breakdown = weekData.content_breakdown || {};

            for(const key in breakdown){

                if(type === "posts" && !["Posts","Photos","Albums","Carousel"].includes(key)) continue;
                if(type === "stories" && key !== "Stories") continue;
                if(type === "reels" && !["Videos","Reels"].includes(key)) continue;

                views += breakdown[key].views || 0;
            }
        }

        values.push(views);
    });

    if (modalChartInstance) modalChartInstance.destroy();

    modalChartInstance = new Chart(
        document.getElementById('modalChart'),
        {
            type: 'line', // 👈 better for trends
            data: {
                labels: labels,
                datasets: [{
                    label: 'Views',
                    data: values,

                     // 👇 ADD THESE
                    backgroundColor: '#f7931a',
                    borderColor: '#f7931a',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        }
        );
}

function initModalTabs(platform){

    const tabs = document.querySelectorAll(".modal-tabs .tab");

    tabs.forEach(tab => {

        tab.addEventListener("click", async function(){

            tabs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");

            const type = this.dataset.type;

            renderModalStats(type, modalDataCache.aggregated);
            loadModalBreakdown(platform, type);
            loadModalChart(platform, type);

        });

    });

}

function loadModalBreakdown(platform, type="all"){

    const data = modalDataCache.aggregated;

    const tbody = document.getElementById("modalBreakdown");
    tbody.innerHTML = "";

    const breakdown = data.content_breakdown || {};

    if(Object.keys(breakdown).length === 0){

        tbody.innerHTML = `
        <tr>
            <td colspan="4" style="text-align:center">
                No content for this period
            </td>
        </tr>`;

        return;
    }

    for(const key in breakdown){

        if(type !== "all"){

            if(type === "posts" && !/post|album|photo|carousel/i.test(key)) continue;
            if(type === "stories" && key !== "Stories") continue;
            if(type === "reels" && !["Videos","Reels"].includes(key)) continue;

        }

        const row = breakdown[key];

        tbody.innerHTML += `
        <tr>
            <td>${key}</td>
            <td>${row.posts}</td>
            <td>${row.engagements}</td>
            <td>${row.views}</td>
        </tr>`;
    }

    renderTopContent(data.top_posts || [], type);
}

function renderTopContent(posts, type="all"){

    const container = document.getElementById("modalTopContent");
    container.innerHTML="";

    if(!posts || posts.length === 0){
        container.innerHTML = `
        <div style="text-align:center;padding:20px;color:#777">
            No content available
        </div>`;
        return;
    }

    let visiblePosts = 0;

    posts.forEach(post=>{

       let postType = (post.type || "").toLowerCase();

       if(type !== "all"){


         if(type === "posts" && !["posts","carousel"].includes(postType)) return;

         if(type === "reels" && !["videos"].includes(postType)) return;

         if(type === "stories" && postType !== "stories") return;

     }



     container.innerHTML += `
        <div style="display:flex;gap:15px;margin-bottom:15px;align-items:center">

            <img src="${post.thumbnail || 'img/placeholder.png'}" 
                 style="width:80px;height:80px;object-fit:cover;border-radius:6px"
                 onerror="this.onerror=null;this.src='img/placeholder.png';" >

            <div>

                <div style="font-weight:bold;margin-bottom:5px">
                    ${post.message || 'No caption'}
                </div>

                <div style="font-size:13px;color:#666">
                    ${new Date(post.created_time).toLocaleDateString()}
                </div>

                <div style="margin-top:5px">
                    👁 ${(post.views || 0).toLocaleString()} |
                    💬 ${(post.engagements || 0).toLocaleString()}
                </div>

                <a href="${post.permalink}" target="_blank" 
                   style="font-size:12px;color:#1877f2">
                    View Post
                </a>

            </div>

     </div>`;

     visiblePosts++;
 });

    if(visiblePosts === 0){
        container.innerHTML = `
        <div style="text-align:center;padding:20px;color:#777">
            No content for this filter
        </div>`;
    }
}

function renderModalStats(type, data){

    const container = document.getElementById("modalStats");


    let views = 0;
    let interactions = 0;
    // const followers = data.new_followers || 0;

    if(type === "all"){

        views = data.reach || 0;
        interactions = data.engagements || 0;

    }else{

        const breakdown = data.content_breakdown || {};

        for(const key in breakdown){

         if(type === "posts" && !["Posts","Albums","Carousel","Photos"].includes(key)) continue;
         if(type === "stories" && key !== "Stories") continue;
         if(type === "reels" && !["Videos","Reels"].includes(key)) continue;

         views += breakdown[key].views || 0;
         interactions += breakdown[key].engagements || 0;

     }

 }
 let html = "";

 if(type === "all"){

    html = `
        <div class="stats">

            <div class="stat-box">
                <h3>Views</h3>
                <p>${views.toLocaleString()}</p>
            </div>

            <div class="stat-box">
                <h3>3-Second Views</h3>
                <p>—</p>
            </div>

            <div class="stat-box">
                <h3>1-Minute Views</h3>
                <p>—</p>
            </div>

            <div class="stat-box">
                <h3>Content Interactions</h3>
                <p>${interactions.toLocaleString()}</p>
            </div>

            <div class="stat-box">
                <h3>Watch Time</h3>
                <p>—</p>
            </div>

    </div>`;
}

if(type === "posts" || type === "stories"){

    html = `
        <div class="stats">

            <div class="stat-box">
                <h3>Views</h3>
                <p>${views.toLocaleString()}</p>
            </div>

            <div class="stat-box">
                <h3>Content Interactions</h3>
                <p>${interactions.toLocaleString()}</p>
            </div>

    </div>`;
}

if(type === "reels"){

    html = `
        <div class="stats">

            <div class="stat-box">
                <h3>Views</h3>
                <p>${views.toLocaleString()}</p>
            </div>

            <div class="stat-box">
                <h3>Content Interactions</h3>
                <p>${interactions.toLocaleString()}</p>
            </div>

            <div class="stat-box">
                <h3>Watch Time</h3>
                <p>—</p>
            </div>

    </div>`;
}

container.innerHTML = html;
}

window.addEventListener("click", function(e){

    const modal = document.getElementById("insightsModal");
    const modalContent = modal.querySelector(".modal-content");

    if(e.target === modal){
        closeInsightsModal();
    }

});


function updateReportMeta(platform) {

    const meta = document.getElementById('reportMeta');

    const labels = {
        facebook: "Facebook",
        instagram: "Instagram",
        twitter: "Twitter",
        linkedin: "LinkedIn"
    };

    meta.innerHTML =
`SMM WEEKLY REPORT - ${labels[platform] || platform}`;
}

