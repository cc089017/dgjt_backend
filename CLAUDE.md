## 작업 지침 (반드시 준수)
1. **대규모 코드 변경 시 사전 허가 필요** - 많은 양의 코드를 한번에 바꿔야 하는 상황이면 사용자 허가 후 진행
2. **더 나은 방향 제안 시 고지 후 승인** - 잘못된 방향이 있다면 "이러이러한 이유로 이런 방향이 더 좋다"고 고지하고 승인받고 진행
3. **기능별 모듈화** - 코드를 하나에 몰아넣지 말고 기능별로 모듈화

## 프로젝트 목적
- 시나리오 기반 모의해킹 실습 환경 구축

## 인프라 아키텍처
- **WEB** (public): nginx, 리버스 프록시 용도로만 사용
- **WAS** (private):
  - frontend: HTML + JS (폴더 별도 존재)
  - backend: PHP + Apache (폴더 별도 존재)
- **RDS** (private)

## 네트워크 접근 제어
- WAS → RDS: **3306 포트로만 접근 가능**
- WEB → WAS: 리버스 프록시 경유
- 외부 → WEB: public

## 공격 시나리오 (Kill Chain)

### 1단계: 정찰 및 일반 사용자 행위
1. **회원가입 / 로그인** — 일반 사용자 계정 생성 및 인증
2. **이미지 업로드 시도 (일반 영역)** — 다층 검증으로 차단되는 것 확인 (정상 보안 동작)

### 2단계: 취약 영역 발견
3. **잔재 페이지 탐색** — 사용 중단된 레거시 페이지 3개가량 노출되어 있음을 확인
4. **다운로드 API 경로 발견 및 동작 검증** — 잔재 페이지 중 1곳을 `curl`로 분석해 다운로드 API 경로를 알아내고, 해당 API를 직접 테스트해 실제로 다운로드가 되는 것을 확인

### 3단계: 인증 우회 (JWT 위조)
5. **임의 파일 다운로드 (LFD)** — 취약한 다운로드 API를 통해 JWT secret key가 포함된 PHP 파일 다운로드
6. **JWT 위조** — 탈취한 secret key로 admin 권한이 담긴 JWT를 `HS256`으로 정상 서명하여 **admin 페이지 접근**

### 4단계: 코드 실행 / 초기 침투
7. **웹쉘 업로드** — admin의 배너 업로드 기능을 악용해 웹쉘 업로드
8. **리버스 쉘 획득** — 웹쉘 통해 WAS에 리버스 쉘 연결

### 5단계: 클라우드 피벗
9. **IMDS 탈취** — WAS에서 AWS 메타데이터 서비스(`169.254.169.254`)에서 IAM 자격증명 탈취
10. **프록시/터널링 구성** — 탈취한 정보 기반으로 터널링 도구를 사용해 RDS 접근 경로 확보

### 6단계: 목적 달성
11. **민감 정보 유출** — RDS 접근을 통해 데이터 탈취 (Exfiltration)

---

## 작업 현황

### 완료
- 백엔드 PHP 라우터 골격 — `auth`, `users`, `products`, `banners`, `misc`
- 의도적 SQL Injection 취약점 — 모든 라우터 (PDO prepared statement 미사용, 문자열 결합)
- JWT 하드코딩 secret — `core/Jwt.php`의 `secret()` fallback 값
- 배너 업로드 (admin) — 확장자 검증 없음, `uploads/banners/`에 저장 → 웹쉘 진입점
- Path Traversal 다운로드 API — `routers/download.php` (`GET /api/download?file=...`)
  - `__DIR__ . '/../files/'` + 사용자 입력 그대로 결합
  - 공격: `?file=../core/Jwt.php` → JWT secret 탈취
- 이미지 업로드 다층 검증 — `isValidProductImage()` (확장자 + MIME + magic bytes)

### 진행 중: 상품 이미지 DB → 파일시스템 전환

**목표**: `product_image.image_data` BLOB → `uploads/products/` 디스크 저장 + Apache 직접 서빙 (banners 패턴과 동일)

**보안 경계 (Defense in Depth)**:
- 앱 검증 (`isValidProductImage` 3중 체크) **유지**
- `uploads/products/.htaccess`로 **PHP 실행 차단** (FilesMatch)
- `uploads/banners/`는 **PHP 실행 허용** (시나리오상 웹쉘 진입점)

#### 사용자 작업 (RDS)
```sql
ALTER TABLE product_image DROP COLUMN image_data;
ALTER TABLE product_image ADD COLUMN image_url VARCHAR(255) NOT NULL;
```

#### 백엔드 작업 (대기 중 — Claude가 처리 예정)
- [ ] `routers/products.php` 수정
  - `fetchThumbnail()`: image_url 직접 반환
  - `GET /api/products/{pid}` (상세): image_urls를 DB의 image_url에서 가져옴
  - `GET /api/products/{pid}/images` (이미지 목록): image_url 반환
  - `GET /api/products/{pid}/images/{order}` (바이너리 서빙) — **삭제** (Apache 직접 서빙)
  - `POST /api/products/{pid}/images` (업로드): `move_uploaded_file`로 디스크 저장 + image_url 기록
  - `uuidv4()` 헬퍼 추가 (function_exists 가드)
- [ ] `uploads/products/.htaccess` 생성 — `<FilesMatch "\.(php|phtml|php3|php4|php5|php7|pht|phar)$">Require all denied</FilesMatch>`
- [ ] `index.php`에 `uploads/products` 디렉토리 보장 코드 추가 (banners 패턴 따라)

