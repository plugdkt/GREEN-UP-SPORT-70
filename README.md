# GREEN UP SPORT 70 — ระบบบริหารจัดการงบประมาณทัพสีเขียว (UP SPORT DAY 2027)

ระบบเว็บแอปพลิเคชันสำหรับรายงานและบริหารจัดการงบประมาณ 3 ก้อน งบสมทบ 9 ส่วนงาน ตารางแข่ง & งบกีฬา ทำเนียบนักกีฬา รายงานเวชภัณฑ์ และการเบิกจ่าย สำหรับทัพสีเขียว กีฬาบุคลากรมหาวิทยาลัยพะเยา พ.ศ. 2570

## เทคโนโลยีที่ใช้
- **Backend:** PHP 8 + PDO (MariaDB/MySQL)
- **Database:** MariaDB 10.11 (`database/schema_and_seed.sql`)
- **Runtime:** Docker Compose (รองรับการรันบน Windows IIS ด้วย `web.config`)

## วิธีรันด้วย Docker
```bash
docker compose up -d --build
```
เปิดใช้งานได้ที่ `http://localhost:8087`
