# BookFlow on Kubernetes

## 파일 구성

| 파일 | 역할 |
|---|---|
| `00-namespace.yaml` | bookflow 네임스페이스 |
| `10-configmap.yaml` | .env 일반 값 (DB host, app url 등) |
| `11-secret.example.yaml` | .env 민감 값 템플릿 (이 파일은 커밋 OK, 실제는 별도 관리) |
| `20-deployment-app.yaml` | Laravel 앱 Deployment + ClusterIP Service |
| `30-deployment-redis.yaml` | Redis Deployment + Service (간이 — 운영은 관리형 권장) |
| `40-ingress.yaml` | booksys.co.kr Ingress + TLS (cert-manager) |
| `50-job-migrate.yaml` | DB 마이그레이션 + 시드 Job |
| `60-hpa.yaml` | 자동 스케일링 (CPU 70%, Memory 80%) |

## 사전 준비

- 클러스터에 다음이 설치되어 있어야 함:
  - **Ingress Controller** (nginx-ingress 또는 traefik)
  - **cert-manager** (Let's Encrypt 자동 발급용) + `letsencrypt-prod` ClusterIssuer
  - **metrics-server** (HPA용)
- 컨테이너 레지스트리 (Harbor / ECR / GCR / Docker Hub)
- 외부 DB (관리형 MySQL/MariaDB 또는 동일 클러스터 내 StatefulSet)
- 도메인 `booksys.co.kr` → Ingress LoadBalancer IP A 레코드

## 첫 배포 절차

```bash
# 1. 이미지 빌드 + 푸시
docker build -t REGISTRY/bookflow:v1.0.0 .
docker push REGISTRY/bookflow:v1.0.0

# 2. 20-deployment-app.yaml과 50-job-migrate.yaml의 image: 필드 갱신

# 3. Secret 생성 (방법 중 하나)
#    A. example 복사 후 값 채우기
cp k8s/11-secret.example.yaml k8s/11-secret.yaml
# 실제 값 입력...
kubectl apply -f k8s/11-secret.yaml

#    B. 또는 명령어로 생성
APP_KEY=$(php artisan key:generate --show)
kubectl create secret generic bookflow-secret -n bookflow \
  --from-literal=APP_KEY="$APP_KEY" \
  --from-literal=DB_USERNAME="bookflow" \
  --from-literal=DB_PASSWORD="strong_password" \
  --from-literal=ALADIN_TTB_KEY="ttbbarijo0654001"
# (나머지 키들도 추가)

# 4. 순서대로 apply
kubectl apply -f k8s/00-namespace.yaml
kubectl apply -f k8s/10-configmap.yaml
kubectl apply -f k8s/30-deployment-redis.yaml
kubectl apply -f k8s/20-deployment-app.yaml
kubectl apply -f k8s/40-ingress.yaml
kubectl apply -f k8s/60-hpa.yaml

# 5. DB 마이그레이션 + 시드 (첫 배포만)
kubectl apply -f k8s/50-job-migrate.yaml
kubectl logs -n bookflow -l role=migrate -f

# 6. 확인
kubectl get pods -n bookflow
kubectl get ingress -n bookflow
curl -I https://booksys.co.kr/up    # 200이면 OK
```

## 일상 배포

```bash
# 1. 새 이미지 빌드/푸시 (CI/CD에서 자동)
docker build -t REGISTRY/bookflow:v1.0.1 .
docker push REGISTRY/bookflow:v1.0.1

# 2. rolling update
kubectl set image deployment/bookflow-app app=REGISTRY/bookflow:v1.0.1 -n bookflow

# 3. 마이그레이션 변경이 있을 때만
kubectl delete job bookflow-migrate -n bookflow --ignore-not-found
kubectl apply -f k8s/50-job-migrate.yaml
```

## 운영 시 주의

| 영역 | 권장 |
|---|---|
| **DB** | 클러스터 외부 관리형 DB (RDS, CloudSQL, 네이버 클라우드 DB 등). 절대 StatefulSet으로 자체 운영 비추 |
| **Redis** | 마찬가지로 관리형 (ElastiCache, Memorystore) 권장 |
| **파일 저장 (도서 표지 등)** | S3 호환 오브젝트 스토리지로 (Laravel `FILESYSTEM_DISK=s3`). PVC는 다중 Pod에서 공유 시 문제 |
| **Secret 관리** | sealed-secrets, external-secrets-operator, HashiCorp Vault 중 택1 |
| **로그** | 컨테이너 stdout/stderr → Loki / EFK / Datadog |
| **모니터링** | Prometheus + Grafana, /up 외에 메트릭 엔드포인트 노출 검토 |
| **백업** | DB는 자동 백업, 도서 마스터 데이터 별도 export |
| **Pod Security** | runAsNonRoot, readOnlyRootFilesystem 적용 검토 (현재는 단순화 위해 제외) |

## 알라딘 TTB Key 도메인 제약 안내

알라딘 OpenAPI 키는 **booksys.co.kr 도메인 한정**. 클러스터의 Ingress가 이 도메인으로 노출되어야 정상 동작.

## 다음 단계 (선택)

- **Helm chart**로 전환 (환경별 values: dev/staging/prod)
- **ArgoCD / FluxCD**로 GitOps
- **CronJob** 추가 (정기 알림, 통계 생성, 백업 등)
- **NetworkPolicy** 추가 (Pod 간 통신 제한)
- **PodDisruptionBudget** 추가 (배포 시 가용성 보장)
