# Cash Flow Forecasting & Financial Health Analytics — Phase 25

> สถานะ: **เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed ณ 2026-09-20)**

```mermaid
flowchart TD
    subgraph DataSources["Financial Inflow & Outflow Sources"]
        AR["AR Due Dates\nCustomer Invoices"]
        Deals["Sales Pipeline\nWeighted by Deal Probability"]
        AP["AP Approved Expenses\nVendor Invoices"]
        PO["Open Purchase Orders\nCommitted Costs"]
        Payroll["Recurring Monthly Payroll\nDay 25 Scheduled Run"]
    end

    subgraph ForecastEngine["CashFlowForecastService (30 - 180 Days Horizon)"]
        InflowCalc["Inflow Calculation Engine\nAR Cash + Deal Upside Factor"]
        OutflowCalc["Outflow Calculation Engine\nCommitted AP + PO + Payroll Buffer"]
        
        Scenarios["3 Simulation Scenarios\nforecast_scenarios"]
        Base["Base Scenario\nNormal Business Operations"]
        Best["Best Scenario\nHigh Deal Win Rate + Faster AR"]
        Worst["Worst Scenario\nCollection Delays + Cost Overruns"]
        
        Scenarios --> Base & Best & Worst
        AR & Deals --> InflowCalc
        AP & PO & Payroll --> OutflowCalc
        
        InflowCalc & OutflowCalc --> SimulationEngine["Trajectory Projection Engine\nDay-by-Day Balance Simulation"]
        Base & Best & Worst --> SimulationEngine
    end

    subgraph HealthAnalytics["Financial Health & Liquidity Ratios"]
        CR["Current Ratio\nCurrent Assets / Liabilities (>= 1.5x)"]
        QR["Quick Ratio\n(Cash + AR) / Liabilities (>= 1.0x)"]
        DSO["Days Sales Outstanding\n90 Days Sales Velocity"]
        DPO["Days Payable Outstanding\n90 Days Expense Velocity"]
        Runway["Working Capital Runway\nMonths before Zero Cash"]
    end

    subgraph Outputs["Visualization & Daily Automation"]
        SVGChart["Interactive SVG Trajectory Curve\nLowest Cash Point & Zero-line Alarm"]
        KPIBadge["Executive StatCards & Badges\nHealth Scores & Comparison Cards"]
        ArtisanCron["Artisan Scheduler\nphp artisan cashflow:snapshot (Daily 02:00)"]
        SnapshotDB[("cash_flow_forecast_snapshots\nfinancial_metric_logs")]
    end

    SimulationEngine --> SVGChart
    SimulationEngine --> HealthAnalytics
    HealthAnalytics --> KPIBadge
    SimulationEngine --> ArtisanCron
    ArtisanCron --> SnapshotDB
```

### รายละเอียดสถาปัตยกรรมและการคำนวณ

1. **Multi-Scenario Modeling:**
   - **Base:** การดำเนินงานตามงวดปกติ คิด Inflow จาก AR 100% และยอดดีลตาม % Probability ที่บันทึกไว้
   - **Best:** สมมติฐานเชิงบวก (Optimistic) รับชำระหนี้เร็วกว่ากำหนด + ดีลขายชนะเพิ่มขึ้นตาม Multiplier
   - **Worst:** การทดสอบภาวะวิกฤต (Stress Test) สมมติฐานหนี้ค้างรับล่าช้า (Haircut) และค่าใช้จ่ายสำรองฉุกเฉิน
2. **Dynamic Day-by-Day Cash Curve:**
   - จำลองยอดเงินสดคงเหลือสะสมวันต่อวัน คำนวณหา "จุดต่ำสุดของเงินสด" (Lowest Cash Point) และวันที่เงินสดจะลดลงต่ำสุด
3. **Liquidity & Working Capital Ratios:**
   - คำนวณอัตราส่วนสภาพคล่องหมุนเวียนเร็ว (Quick Ratio), อัตราส่วนหมุนเวียน (Current Ratio), ระยะเวลาเก็บหนี้เฉลี่ย (DSO), ระยะเวลาชำระหนี้เจ้าหนี้ (DPO) และจำนวนเดือนที่เงินสดจะอยู่รอด (Runway)
4. **Data Isolation & Privacy Guard:**
   - ไม่มีการเปิดเผยตัวเลขยอดเงินสดรวมดิบ (Unreconciled Cash Balance) สู่ภายนอก โดยคำนวณจาก Delta การหมุนเวียนและโมเดลสมมติฐานที่ปิดความเสี่ยง

ดูรายละเอียด implementation และ controller ได้ที่ [`backend/app/Services/Finance/CashFlowForecastService.php`](file:///c:/LocalDevine/www/ERP/backend/app/Services/Finance/CashFlowForecastService.php).
