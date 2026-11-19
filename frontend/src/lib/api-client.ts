import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from "axios";

export interface ApiSuccessResponse<T = unknown> {
  success: true;
  message?: string;
  data: T;
  meta?: {
    pagination?: {
      total: number;
      count: number;
      per_page: number;
      current_page: number;
      total_pages: number;
    };
    timestamp: string;
  };
}

export interface ApiErrorResponse {
  success: false;
  code: string;
  message: string;
  errors?: Record<string, string[]>;
  timestamp: string;
}

const apiClient: AxiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1",
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  withCredentials: true,
});

// Request interceptor to inject Auth Bearer token and Multi-Tenant headers
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("auth_token");
      const tenantId = localStorage.getItem("current_tenant_id");
      const schoolId = localStorage.getItem("current_school_id");

      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }

      if (tenantId) {
        config.headers["X-Tenant-ID"] = tenantId;
      }

      if (schoolId) {
        config.headers["X-School-ID"] = schoolId;
      }
    }

    return config;
  },
  (error) => Promise.reject(error),
);

// Response interceptor to normalize errors
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorResponse>) => {
    if (error.response) {
      // 401 Unauthorized token handling
      if (error.response.status === 401 && typeof window !== "undefined") {
        if (!window.location.pathname.startsWith("/login")) {
          localStorage.removeItem("auth_token");
          // window.location.href = "/login";
        }
      }

      const apiError: ApiErrorResponse = error.response.data || {
        success: false,
        code: `HTTP_${error.response.status}`,
        message: error.message || "An unexpected error occurred.",
        timestamp: new Date().toISOString(),
      };

      return Promise.reject(apiError);
    }

    return Promise.reject({
      success: false,
      code: "NETWORK_ERROR",
      message: error.message || "Network connection failure. Please check your internet connection.",
      timestamp: new Date().toISOString(),
    });
  },
);

export default apiClient;

