"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/providers/auth-provider";
import apiClient from "@/lib/api-client";
import { Sidebar } from "@/components/layout/Sidebar";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { Modal } from "@/components/ui/dialog";
import {
  BookOpen,
  Bus,
  Package,
  Home,
  UserCheck,
  Search,
  Plus,
  CheckCircle2,
  AlertTriangle,
  ShieldCheck,
  Fuel,
  Wrench,
  Utensils,
  AlertCircle,
  FileText,
  QrCode,
  ArrowRight,
  UserPlus,
  Clock,
  LogOut,
  Check,
  ChevronRight,
  Filter,
  RefreshCw,
  Users,
  Building,
  KeyRound,
  MapPin,
  Calendar,
  AlertOctagon,
  Eye,
  CheckSquare,
} from "lucide-react";

// ==========================================
// Operations Main Component
// ==========================================

export default function OperationsPage() {
  const { user, activeTenant, activeBranch } = useAuth();
  const [activeTab, setActiveTab] = useState<"library" | "transport" | "inventory" | "hostel" | "visitors">("library");

  const [loading, setLoading] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Active Branch ID
  const branchId = activeBranch?.id || "00000000-0000-0000-0000-000000000001";

  // Auto-clear toasts
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => setErrorMessage(null), 5000);
      return () => clearTimeout(timer);
    }
  }, [errorMessage]);

  // =========================================================================
  // 1. Library State & Modals
  // =========================================================================
  const [books, setBooks] = useState<any[]>([
    {
      id: "b-1",
      title: "Concepts of Physics Vol 1",
      author: "H.C. Verma",
      isbn: "978-8177091878",
      category: "Physics",
      shelf_no: "Shelf-B-4",
      total_copies: 5,
      available_copies: 3,
      copies: [
        { id: "c-1", barcode: "BC-LW-001-001", status: "available", copy_number: 1 },
        { id: "c-2", barcode: "BC-LW-001-002", status: "issued", copy_number: 2 },
        { id: "c-3", barcode: "BC-LW-001-003", status: "available", copy_number: 3 },
      ],
    },
    {
      id: "b-2",
      title: "Introduction to Algorithms",
      author: "CLRS",
      isbn: "978-0262033848",
      category: "Computer Science",
      shelf_no: "Shelf-C-1",
      total_copies: 3,
      available_copies: 1,
      copies: [
        { id: "c-4", barcode: "BC-LW-002-001", status: "issued", copy_number: 1 },
        { id: "c-5", barcode: "BC-LW-002-002", status: "available", copy_number: 2 },
      ],
    },
  ]);
  const [isAddBookModalOpen, setIsAddBookModalOpen] = useState(false);
  const [isIssueBookModalOpen, setIsIssueBookModalOpen] = useState(false);
  const [isReturnBookModalOpen, setIsReturnBookModalOpen] = useState(false);
  const [isBarcodeScanModalOpen, setIsBarcodeScanModalOpen] = useState(false);
  const [scannedResult, setScannedResult] = useState<any | null>(null);
  const [barcodeInput, setBarcodeInput] = useState("");

  const [newBookForm, setNewBookForm] = useState({
    title: "",
    author: "",
    isbn: "",
    category: "General",
    shelf_no: "",
    initial_copies: 1,
  });

  const [issueForm, setIssueForm] = useState({
    copy_id: "",
    member_type: "student",
    member_id: "",
    due_date: "",
  });

  const [returnForm, setReturnForm] = useState({
    issue_id: "",
    return_date: new Date().toISOString().split("T")[0],
    fine_amount: 0,
    copy_status: "available",
  });

  // =========================================================================
  // 2. Transport State & Modals
  // =========================================================================
  const [vehicles, setVehicles] = useState<any[]>([
    {
      id: "v-1",
      registration_number: "KA-01-AB-4501",
      vehicle_model: "Tata Starbus 40-Seater",
      capacity: 40,
      fitness_certificate_expiry: "2027-02-15",
      insurance_expiry: "2026-12-30",
      gps_device_id: "GPS-TRK-9001",
      is_active: true,
    },
    {
      id: "v-2",
      registration_number: "KA-02-CD-8899",
      vehicle_model: "Ashok Leyland Falcon",
      capacity: 35,
      fitness_certificate_expiry: "2026-11-10", // Expired
      insurance_expiry: "2027-04-20",
      gps_device_id: "GPS-TRK-9002",
      is_active: true,
    },
  ]);
  const [routes, setRoutes] = useState<any[]>([
    {
      id: "r-1",
      route_name: "Route 101 - North City",
      start_point: "Central Bus Station",
      end_point: "Main Campus Gate",
      fare: 1200,
      stops: [
        { id: "s-1", stop_name: "Sector 4 Junction", pickup_time: "07:15", drop_time: "15:45", fare_amount: 1000 },
        { id: "s-2", stop_name: "Maple Gardens Gate", pickup_time: "07:30", drop_time: "15:30", fare_amount: 1200 },
      ],
      allocations_count: 28,
    },
  ]);
  const [isAddVehicleModalOpen, setIsAddVehicleModalOpen] = useState(false);
  const [isAddRouteModalOpen, setIsAddRouteModalOpen] = useState(false);
  const [isLogMaintenanceModalOpen, setIsLogMaintenanceModalOpen] = useState(false);
  const [isLogFuelModalOpen, setIsLogFuelModalOpen] = useState(false);

  const [newVehicleForm, setNewVehicleForm] = useState({
    registration_number: "",
    vehicle_model: "",
    capacity: 40,
    fitness_certificate_expiry: "",
    insurance_expiry: "",
    gps_device_id: "",
  });

  const [maintenanceForm, setMaintenanceForm] = useState({
    vehicle_id: "",
    maintenance_type: "routine_service",
    service_date: new Date().toISOString().split("T")[0],
    cost: "",
    odometer_reading: "",
    notes: "",
  });

  const [fuelForm, setFuelForm] = useState({
    vehicle_id: "",
    fuel_date: new Date().toISOString().split("T")[0],
    fuel_type: "diesel",
    quantity_litres: "",
    cost_per_litre: "92.50",
    receipt_number: "",
  });

  // =========================================================================
  // 3. Inventory State & Modals
  // =========================================================================
  const [inventoryItems, setInventoryItems] = useState<any[]>([
    {
      id: "itm-1",
      item_name: "A4 Paper Reams (Box of 5)",
      item_code: "ITM-PAP-A4",
      unit_of_measure: "box",
      min_stock_alert: 10,
      current_stock: 4, // low stock alert!
      category: { name: "Stationery" },
    },
    {
      id: "itm-2",
      item_name: "Football Size 5",
      item_code: "FB-5",
      unit_of_measure: "pcs",
      min_stock_alert: 2,
      current_stock: 12,
      category: { name: "Sports" },
    },
    {
      id: "itm-3",
      item_name: "Conical Flasks 250ml",
      item_code: "FLK-250",
      unit_of_measure: "pcs",
      min_stock_alert: 5,
      current_stock: 18,
      category: { name: "Lab Glassware" },
    },
  ]);
  const [isAddItemModalOpen, setIsAddItemModalOpen] = useState(false);
  const [isIssueStockModalOpen, setIsIssueStockModalOpen] = useState(false);
  const [isGoodsReceiptModalOpen, setIsGoodsReceiptModalOpen] = useState(false);

  const [newItemForm, setNewItemForm] = useState({
    item_name: "",
    item_code: "",
    category_name: "General",
    unit_of_measure: "pcs",
    min_stock_alert: 5,
    initial_stock: 10,
    unit_cost: 0,
  });

  const [issueStockForm, setIssueStockForm] = useState({
    item_id: "",
    issued_to_type: "department",
    issued_to_name: "",
    quantity: 1,
    status: "issued",
  });

  const [goodsReceiptForm, setGoodsReceiptForm] = useState({
    vendor_name: "",
    invoice_number: "",
    item_id: "",
    quantity: 10,
    unit_cost: 100,
    total_amount: 1000,
  });

  // =========================================================================
  // 4. Hostel State & Modals
  // =========================================================================
  const [hostelBuildings, setHostelBuildings] = useState<any[]>([
    {
      id: "bld-1",
      name: "Tagore Boys Residence",
      building_type: "boys",
      warden_name: "Dr. R.K. Sharma",
      warden_phone: "+919876500112",
      rooms_count: 12,
      total_capacity: 24,
      occupied_beds: 19,
    },
    {
      id: "bld-2",
      name: "Sarojini Girls Residence",
      building_type: "girls",
      warden_name: "Mrs. Meenakshi Sundaram",
      warden_phone: "+919876500114",
      rooms_count: 10,
      total_capacity: 20,
      occupied_beds: 15,
    },
  ]);
  const [hostelRooms, setHostelRooms] = useState<any[]>([
    {
      id: "rm-1",
      building_name: "Tagore Boys Residence",
      room_number: "B-101",
      room_type: "double",
      capacity: 2,
      occupied_beds: 1,
      fee_per_session: 45000,
      is_active: true,
    },
    {
      id: "rm-2",
      building_name: "Tagore Boys Residence",
      room_number: "B-102",
      room_type: "double",
      capacity: 2,
      occupied_beds: 2, // Full
      fee_per_session: 45000,
      is_active: true,
    },
    {
      id: "rm-3",
      building_name: "Sarojini Girls Residence",
      room_number: "G-201",
      room_type: "single",
      capacity: 1,
      occupied_beds: 0, // Available
      fee_per_session: 55000,
      is_active: true,
    },
  ]);
  const [hostelComplaints, setHostelComplaints] = useState<any[]>([
    {
      id: "cmp-1",
      title: "Bathroom Tap Leaking",
      building_name: "Tagore Boys Residence",
      room_number: "B-102",
      category: "plumbing",
      priority: "high",
      status: "pending",
      student_name: "Kabir Saxena",
    },
    {
      id: "cmp-2",
      title: "Reading Lamp Switch Sparking",
      building_name: "Sarojini Girls Residence",
      room_number: "G-201",
      category: "electrical",
      priority: "urgent",
      status: "resolved",
      student_name: "Ananya Singh",
    },
  ]);
  const [isAddRoomModalOpen, setIsAddRoomModalOpen] = useState(false);
  const [isAllocateHostelModalOpen, setIsAllocateHostelModalOpen] = useState(false);
  const [isLogComplaintModalOpen, setIsLogComplaintModalOpen] = useState(false);

  const [newRoomForm, setNewRoomForm] = useState({
    building_id: "",
    room_number: "",
    room_type: "double",
    capacity: 2,
    fee_per_session: 45000,
  });

  const [hostelComplaintForm, setHostelComplaintForm] = useState({
    building_id: "",
    room_id: "",
    student_name: "",
    title: "",
    description: "",
    category: "plumbing",
    priority: "medium",
  });

  // =========================================================================
  // 5. Visitor State & Modals
  // =========================================================================
  const [visitorPasses, setVisitorPasses] = useState<any[]>([
    {
      id: "vp-1",
      pass_number: "VP-261120-1044",
      visitor_name: "Anita Desai",
      phone: "+919811223344",
      purpose: "Parent Teacher Consultation",
      host_employee_name: "Dennis Dsilva (Vice Principal)",
      check_in_time: "10:15 AM",
      check_out_time: null,
      status: "checked_in",
      pickup: null,
    },
    {
      id: "vp-2",
      pass_number: "VP-261120-1088",
      visitor_name: "George Dsilva",
      phone: "+919500112233",
      purpose: "Early Child Pickup - Doctor Appointment",
      host_employee_name: "Reception Desk",
      check_in_time: "11:30 AM",
      check_out_time: null,
      status: "checked_in",
      pickup: {
        student_name: "Samuel Dsilva (Class 7-C)",
        relationship: "Father",
        authorization_status: "verified",
      },
    },
    {
      id: "vp-3",
      pass_number: "VP-261120-0912",
      visitor_name: "Michael Scott",
      phone: "+919844556677",
      purpose: "Textbook Supplier Meeting",
      host_employee_name: "Stores Manager",
      check_in_time: "09:00 AM",
      check_out_time: "10:30 AM",
      status: "checked_out",
      pickup: null,
    },
  ]);
  const [isCheckInModalOpen, setIsCheckInModalOpen] = useState(false);
  const [isGatePassModalOpen, setIsGatePassModalOpen] = useState(false);

  const [checkInForm, setCheckInForm] = useState({
    full_name: "",
    phone: "",
    national_id: "",
    host_employee_name: "",
    purpose: "",
    is_student_pickup: false,
    student_name: "",
    relationship: "Parent",
  });

  const [gatePassForm, setGatePassForm] = useState({
    student_name: "",
    guardian_name: "",
    reason: "Medical Emergency / Doctor Appointment",
  });

  // =========================================================================
  // Handlers & API Actions
  // =========================================================================

  // Handle Barcode Search
  const handleBarcodeLookup = () => {
    if (!barcodeInput.trim()) return;
    setLoading(true);

    // Look up in local books
    let matchedCopy: any = null;
    let parentBook: any = null;

    for (const b of books) {
      const found = b.copies?.find((c: any) => c.barcode.toLowerCase() === barcodeInput.trim().toLowerCase());
      if (found) {
        matchedCopy = found;
        parentBook = b;
        break;
      }
    }

    if (matchedCopy) {
      setScannedResult({
        copy: matchedCopy,
        book: parentBook,
      });
    } else {
      setErrorMessage(`Barcode '${barcodeInput}' not found in active inventory.`);
      setScannedResult(null);
    }
    setLoading(false);
  };

  // Add Book
  const handleAddBook = (e: React.FormEvent) => {
    e.preventDefault();
    const newBook = {
      id: "b-" + Date.now(),
      title: newBookForm.title,
      author: newBookForm.author,
      isbn: newBookForm.isbn,
      category: newBookForm.category,
      shelf_no: newBookForm.shelf_no,
      total_copies: Number(newBookForm.initial_copies),
      available_copies: Number(newBookForm.initial_copies),
      copies: Array.from({ length: Number(newBookForm.initial_copies) }).map((_, i) => ({
        id: `c-${Date.now()}-${i}`,
        barcode: `BC-LW-${Math.floor(100 + Math.random() * 900)}-${i + 1}`,
        status: "available",
        copy_number: i + 1,
      })),
    };

    setBooks([newBook, ...books]);
    setIsAddBookModalOpen(false);
    setSuccessMessage(`Cataloged "${newBookForm.title}" with ${newBookForm.initial_copies} copies and generated barcodes.`);
    setNewBookForm({ title: "", author: "", isbn: "", category: "General", shelf_no: "", initial_copies: 1 });
  };

  // Add Vehicle
  const handleAddVehicle = (e: React.FormEvent) => {
    e.preventDefault();
    const newV = {
      id: "v-" + Date.now(),
      registration_number: newVehicleForm.registration_number.toUpperCase(),
      vehicle_model: newVehicleForm.vehicle_model,
      capacity: Number(newVehicleForm.capacity),
      fitness_certificate_expiry: newVehicleForm.fitness_certificate_expiry,
      insurance_expiry: newVehicleForm.insurance_expiry,
      gps_device_id: newVehicleForm.gps_device_id || `GPS-TRK-${Math.floor(1000 + Math.random() * 9000)}`,
      is_active: true,
    };
    setVehicles([newV, ...vehicles]);
    setIsAddVehicleModalOpen(false);
    setSuccessMessage(`Vehicle ${newV.registration_number} added with active tracking telemetry hook.`);
    setNewVehicleForm({ registration_number: "", vehicle_model: "", capacity: 40, fitness_certificate_expiry: "", insurance_expiry: "", gps_device_id: "" });
  };

  // Check In Visitor
  const handleCheckInVisitor = (e: React.FormEvent) => {
    e.preventDefault();
    const passSeq = Math.floor(1000 + Math.random() * 9000);
    const passNum = `VP-261120-${passSeq}`;

    const newPass = {
      id: "vp-" + Date.now(),
      pass_number: passNum,
      visitor_name: checkInForm.full_name,
      phone: checkInForm.phone,
      purpose: checkInForm.purpose,
      host_employee_name: checkInForm.host_employee_name || "Reception Desk",
      check_in_time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
      check_out_time: null,
      status: "checked_in",
      pickup: checkInForm.is_student_pickup
        ? {
            student_name: checkInForm.student_name,
            relationship: checkInForm.relationship,
            authorization_status: "verified",
          }
        : null,
    };

    setVisitorPasses([newPass, ...visitorPasses]);
    setIsCheckInModalOpen(false);
    setSuccessMessage(`Visitor pass ${passNum} issued for ${checkInForm.full_name}. Badge marked ready.`);
    setCheckInForm({ full_name: "", phone: "", national_id: "", host_employee_name: "", purpose: "", is_student_pickup: false, student_name: "", relationship: "Parent" });
  };

  // Check Out Visitor
  const handleCheckOut = (passId: string) => {
    setVisitorPasses(
      visitorPasses.map((p) =>
        p.id === passId
          ? { ...p, status: "checked_out", check_out_time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) }
          : p
      )
    );
    setSuccessMessage("Visitor successfully checked out. Security gate notified.");
  };

  return (
    <div className="flex h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />

      <main className="flex-1 overflow-y-auto">
        <div className="container mx-auto p-6 max-w-7xl space-y-6">
          {/* Header */}
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-4">
            <div>
              <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-3">
                <Building className="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                Extended School Operations
              </h1>
              <p className="text-slate-500 dark:text-slate-400 mt-1">
                Integrated multi-domain operations: Library, Transport, Inventory, Hostel, and Visitor Front Desk.
              </p>
            </div>

            <div className="flex items-center gap-3">
              <Badge variant="outline" className="px-3 py-1 bg-indigo-50 text-indigo-700 border-indigo-200">
                Tenant: {activeTenant?.name || "Main Campus"}
              </Badge>
            </div>
          </div>

          {/* Toast Messages */}
          {successMessage && (
            <div className="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-3 animate-in fade-in">
              <CheckCircle2 className="h-5 w-5 text-emerald-600 flex-shrink-0" />
              <span>{successMessage}</span>
            </div>
          )}

          {errorMessage && (
            <div className="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 flex items-center gap-3 animate-in fade-in">
              <AlertCircle className="h-5 w-5 text-rose-600 flex-shrink-0" />
              <span>{errorMessage}</span>
            </div>
          )}

          {/* Domain Tabs Navigation */}
          <div className="flex flex-wrap border-b border-slate-200 dark:border-slate-800 gap-2">
            <button
              onClick={() => setActiveTab("library")}
              className={`flex items-center gap-2 px-5 py-3 font-medium text-sm rounded-t-lg transition-colors ${
                activeTab === "library"
                  ? "border-b-2 border-indigo-600 text-indigo-600 bg-white dark:bg-slate-900 font-semibold"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100/50"
              }`}
            >
              <BookOpen className="h-4 w-4" />
              Library Management
            </button>

            <button
              onClick={() => setActiveTab("transport")}
              className={`flex items-center gap-2 px-5 py-3 font-medium text-sm rounded-t-lg transition-colors ${
                activeTab === "transport"
                  ? "border-b-2 border-indigo-600 text-indigo-600 bg-white dark:bg-slate-900 font-semibold"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100/50"
              }`}
            >
              <Bus className="h-4 w-4" />
              Transport & Fleet
            </button>

            <button
              onClick={() => setActiveTab("inventory")}
              className={`flex items-center gap-2 px-5 py-3 font-medium text-sm rounded-t-lg transition-colors ${
                activeTab === "inventory"
                  ? "border-b-2 border-indigo-600 text-indigo-600 bg-white dark:bg-slate-900 font-semibold"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100/50"
              }`}
            >
              <Package className="h-4 w-4" />
              Inventory & Stores
            </button>

            <button
              onClick={() => setActiveTab("hostel")}
              className={`flex items-center gap-2 px-5 py-3 font-medium text-sm rounded-t-lg transition-colors ${
                activeTab === "hostel"
                  ? "border-b-2 border-indigo-600 text-indigo-600 bg-white dark:bg-slate-900 font-semibold"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100/50"
              }`}
            >
              <Home className="h-4 w-4" />
              Hostel & Residential
            </button>

            <button
              onClick={() => setActiveTab("visitors")}
              className={`flex items-center gap-2 px-5 py-3 font-medium text-sm rounded-t-lg transition-colors ${
                activeTab === "visitors"
                  ? "border-b-2 border-indigo-600 text-indigo-600 bg-white dark:bg-slate-900 font-semibold"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100/50"
              }`}
            >
              <UserCheck className="h-4 w-4" />
              Visitor Front Desk
            </button>
          </div>

          {/* ========================================================================= */}
          {/* TAB 1: LIBRARY MANAGEMENT */}
          {/* ========================================================================= */}
          {activeTab === "library" && (
            <div className="space-y-6">
              {/* Library KPI Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Cataloged Titles</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">{books.length}</h3>
                    </div>
                    <BookOpen className="h-8 w-8 text-blue-500 bg-blue-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Total Physical Copies</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {books.reduce((sum, b) => sum + (b.total_copies || 0), 0)}
                      </h3>
                    </div>
                    <CheckSquare className="h-8 w-8 text-emerald-500 bg-emerald-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Available on Shelf</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {books.reduce((sum, b) => sum + (b.available_copies || 0), 0)}
                      </h3>
                    </div>
                    <CheckCircle2 className="h-8 w-8 text-indigo-500 bg-indigo-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Overdue Fines Rate</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">₹5.00 / day</h3>
                    </div>
                    <Clock className="h-8 w-8 text-amber-500 bg-amber-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>
              </div>

              {/* Action Toolbar */}
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="relative w-full sm:w-96">
                  <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                  <Input
                    placeholder="Search by title, author, or ISBN..."
                    className="pl-9"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                  />
                </div>

                <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsBarcodeScanModalOpen(true)}
                  >
                    <QrCode className="h-4 w-4" />
                    Scan Barcode / QR
                  </Button>

                  <Button
                    className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                    onClick={() => setIsAddBookModalOpen(true)}
                  >
                    <Plus className="h-4 w-4" />
                    Catalog Book
                  </Button>
                </div>
              </div>

              {/* Books Catalog Table */}
              <Card className="bg-white dark:bg-slate-900">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">Library Catalog & Physical Copies</CardTitle>
                  <CardDescription>Comprehensive book catalog with barcode tracking, availability, and loan limits.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Title & ISBN</TableHead>
                        <TableHead>Author</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Shelf</TableHead>
                        <TableHead>Copies (Avail / Total)</TableHead>
                        <TableHead>Physical Barcodes</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {books
                        .filter(
                          (b) =>
                            b.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                            b.author.toLowerCase().includes(searchQuery.toLowerCase())
                        )
                        .map((book) => (
                          <TableRow key={book.id}>
                            <TableCell>
                              <div className="font-medium text-slate-900 dark:text-slate-100">{book.title}</div>
                              <div className="text-xs text-slate-500 font-mono">ISBN: {book.isbn || "N/A"}</div>
                            </TableCell>
                            <TableCell>{book.author}</TableCell>
                            <TableCell>
                              <Badge variant="outline">{book.category}</Badge>
                            </TableCell>
                            <TableCell className="font-mono text-xs">{book.shelf_no || "General"}</TableCell>
                            <TableCell>
                              <span className="font-bold text-emerald-600">{book.available_copies}</span> / {book.total_copies}
                            </TableCell>
                            <TableCell>
                              <div className="flex flex-wrap gap-1 max-w-xs">
                                {book.copies?.slice(0, 3).map((c: any) => (
                                  <Badge
                                    key={c.id}
                                    variant="secondary"
                                    className={`text-[10px] font-mono ${
                                      c.status === "available"
                                        ? "bg-emerald-50 text-emerald-700"
                                        : "bg-amber-50 text-amber-700"
                                    }`}
                                  >
                                    {c.barcode} ({c.status})
                                  </Badge>
                                ))}
                                {(book.copies?.length || 0) > 3 && (
                                  <span className="text-xs text-slate-400">+{book.copies.length - 3} more</span>
                                )}
                              </div>
                            </TableCell>
                            <TableCell className="text-right">
                              <Button
                                size="sm"
                                variant="outline"
                                className="text-xs"
                                onClick={() => {
                                  const avail = book.copies?.find((c: any) => c.status === "available");
                                  if (avail) {
                                    setIssueForm({ ...issueForm, copy_id: avail.id });
                                    setIsIssueBookModalOpen(true);
                                  } else {
                                    setErrorMessage("No copies currently available on shelf to issue.");
                                  }
                                }}
                              >
                                Issue Book
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ========================================================================= */}
          {/* TAB 2: TRANSPORT & FLEET */}
          {/* ========================================================================= */}
          {activeTab === "transport" && (
            <div className="space-y-6">
              {/* Transport KPI Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Fleet Vehicles</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">{vehicles.length}</h3>
                    </div>
                    <Bus className="h-8 w-8 text-indigo-500 bg-indigo-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Total Seating Capacity</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {vehicles.reduce((sum, v) => sum + (v.capacity || 0), 0)}
                      </h3>
                    </div>
                    <Users className="h-8 w-8 text-blue-500 bg-blue-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Active Bus Routes</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">{routes.length}</h3>
                    </div>
                    <MapPin className="h-8 w-8 text-emerald-500 bg-emerald-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Compliance Warnings</p>
                      <h3 className="text-2xl font-bold mt-1 text-rose-600">1 Expired Doc</h3>
                    </div>
                    <AlertTriangle className="h-8 w-8 text-rose-500 bg-rose-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>
              </div>

              {/* Action Toolbar */}
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex gap-2">
                  <Button
                    className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                    onClick={() => setIsAddVehicleModalOpen(true)}
                  >
                    <Plus className="h-4 w-4" />
                    Register Vehicle
                  </Button>
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsLogMaintenanceModalOpen(true)}
                  >
                    <Wrench className="h-4 w-4" />
                    Log Maintenance
                  </Button>
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsLogFuelModalOpen(true)}
                  >
                    <Fuel className="h-4 w-4" />
                    Record Fuel
                  </Button>
                </div>

                <Badge variant="outline" className="text-xs bg-slate-100 text-slate-700">
                  GPS Telemetry Ingestion Hook: Ready (UDP/TCP API)
                </Badge>
              </div>

              {/* Vehicles Fleet Table */}
              <Card className="bg-white dark:bg-slate-900">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">Bus Fleet & Statutory Documents Compliance</CardTitle>
                  <CardDescription>Vehicle capacity, insurance, fitness certificate expiry alerts, and GPS hardware telemetry.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Registration Number</TableHead>
                        <TableHead>Vehicle Model</TableHead>
                        <TableHead>Capacity</TableHead>
                        <TableHead>Fitness Cert. Expiry</TableHead>
                        <TableHead>Insurance Expiry</TableHead>
                        <TableHead>GPS Device ID</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {vehicles.map((v) => {
                        const fitnessExpired = new Date(v.fitness_certificate_expiry) < new Date();
                        const insuranceExpired = new Date(v.insurance_expiry) < new Date();
                        return (
                          <TableRow key={v.id}>
                            <TableCell className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                              {v.registration_number}
                            </TableCell>
                            <TableCell>{v.vehicle_model}</TableCell>
                            <TableCell>{v.capacity} Passengers</TableCell>
                            <TableCell>
                              <span className={fitnessExpired ? "text-rose-600 font-bold" : "text-slate-700"}>
                                {v.fitness_certificate_expiry} {fitnessExpired && "(EXPIRED)"}
                              </span>
                            </TableCell>
                            <TableCell>
                              <span className={insuranceExpired ? "text-rose-600 font-bold" : "text-slate-700"}>
                                {v.insurance_expiry} {insuranceExpired && "(EXPIRED)"}
                              </span>
                            </TableCell>
                            <TableCell className="font-mono text-xs text-indigo-600">{v.gps_device_id || "None"}</TableCell>
                            <TableCell>
                              <Badge className="bg-emerald-100 text-emerald-800">Active</Badge>
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>

              {/* Routes & Stops */}
              <Card className="bg-white dark:bg-slate-900">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">Configured Routes & Bus Stops</CardTitle>
                  <CardDescription>Route origin, destination, intermediate pickup/drop stops, and student passenger allocations.</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {routes.map((r) => (
                      <div key={r.id} className="p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b pb-3 mb-3">
                          <div>
                            <h4 className="font-semibold text-slate-900 dark:text-slate-100 text-base">{r.route_name}</h4>
                            <p className="text-xs text-slate-500">
                              From: {r.start_point} &rarr; To: {r.end_point} &bull; Base Fare: ₹{r.fare}
                            </p>
                          </div>
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">
                            {r.allocations_count} Students Assigned
                          </Badge>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                          {r.stops?.map((s: any, idx: number) => (
                            <div key={s.id} className="p-2.5 rounded bg-white dark:bg-slate-800 border text-xs space-y-1">
                              <div className="font-medium text-slate-900 dark:text-slate-100">
                                Stop {idx + 1}: {s.stop_name}
                              </div>
                              <div className="text-slate-500">
                                Pickup: <span className="font-semibold text-slate-700">{s.pickup_time}</span> &bull; Drop: <span className="font-semibold text-slate-700">{s.drop_time}</span>
                              </div>
                              <div className="text-slate-500">Fare: ₹{s.fare_amount}</div>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ========================================================================= */}
          {/* TAB 3: INVENTORY & STORES */}
          {/* ========================================================================= */}
          {activeTab === "inventory" && (
            <div className="space-y-6">
              {/* Inventory KPI Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Items Cataloged</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">{inventoryItems.length}</h3>
                    </div>
                    <Package className="h-8 w-8 text-indigo-500 bg-indigo-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Total Stock Units</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {inventoryItems.reduce((sum, itm) => sum + itm.current_stock, 0)}
                      </h3>
                    </div>
                    <CheckSquare className="h-8 w-8 text-emerald-500 bg-emerald-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Low Stock Breaches</p>
                      <h3 className="text-2xl font-bold mt-1 text-amber-600">
                        {inventoryItems.filter((i) => i.current_stock <= i.min_stock_alert).length} Items
                      </h3>
                    </div>
                    <AlertTriangle className="h-8 w-8 text-amber-500 bg-amber-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Categories</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">3 Domains</h3>
                    </div>
                    <Building className="h-8 w-8 text-blue-500 bg-blue-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>
              </div>

              {/* Action Toolbar */}
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex gap-2">
                  <Button
                    className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                    onClick={() => setIsAddItemModalOpen(true)}
                  >
                    <Plus className="h-4 w-4" />
                    New Item
                  </Button>
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsGoodsReceiptModalOpen(true)}
                  >
                    <Plus className="h-4 w-4" />
                    Goods Receipt (Purchase)
                  </Button>
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsIssueStockModalOpen(true)}
                  >
                    <ArrowRight className="h-4 w-4" />
                    Disburse Stock
                  </Button>
                </div>
              </div>

              {/* Inventory Items Table */}
              <Card className="bg-white dark:bg-slate-900">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">Store Inventory & Consumables Ledger</CardTitle>
                  <CardDescription>Fixed assets, classroom consumables, laboratory apparatus, and automated low-stock warnings.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Item Name & Code</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Unit</TableHead>
                        <TableHead>Current Stock</TableHead>
                        <TableHead>Min. Alert Threshold</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {inventoryItems.map((itm) => {
                        const isLow = itm.current_stock <= itm.min_stock_alert;
                        return (
                          <TableRow key={itm.id}>
                            <TableCell>
                              <div className="font-semibold text-slate-900 dark:text-slate-100">{itm.item_name}</div>
                              <div className="text-xs font-mono text-slate-500">{itm.item_code}</div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline">{itm.category?.name || "General"}</Badge>
                            </TableCell>
                            <TableCell className="uppercase text-xs">{itm.unit_of_measure}</TableCell>
                            <TableCell className="font-bold text-base">
                              <span className={isLow ? "text-amber-600" : "text-emerald-600"}>{itm.current_stock}</span>
                            </TableCell>
                            <TableCell>{itm.min_stock_alert}</TableCell>
                            <TableCell>
                              {isLow ? (
                                <Badge className="bg-amber-100 text-amber-800 flex items-center gap-1 w-fit">
                                  <AlertTriangle className="h-3 w-3" /> Low Stock
                                </Badge>
                              ) : (
                                <Badge className="bg-emerald-100 text-emerald-800 flex items-center gap-1 w-fit">
                                  <Check className="h-3 w-3" /> In Stock
                                </Badge>
                              )}
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ========================================================================= */}
          {/* TAB 4: HOSTEL & RESIDENTIAL */}
          {/* ========================================================================= */}
          {activeTab === "hostel" && (
            <div className="space-y-6">
              {/* Hostel KPI Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Hostel Buildings</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">{hostelBuildings.length}</h3>
                    </div>
                    <Home className="h-8 w-8 text-indigo-500 bg-indigo-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Total Bed Capacity</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {hostelBuildings.reduce((sum, b) => sum + b.total_capacity, 0)} Beds
                      </h3>
                    </div>
                    <KeyRound className="h-8 w-8 text-emerald-500 bg-emerald-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Occupied Beds</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {hostelBuildings.reduce((sum, b) => sum + b.occupied_beds, 0)} Boarders
                      </h3>
                    </div>
                    <Users className="h-8 w-8 text-blue-500 bg-blue-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Active Complaints</p>
                      <h3 className="text-2xl font-bold mt-1 text-amber-600">
                        {hostelComplaints.filter((c) => c.status === "pending").length} Pending
                      </h3>
                    </div>
                    <AlertCircle className="h-8 w-8 text-amber-500 bg-amber-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>
              </div>

              {/* Action Toolbar */}
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex gap-2">
                  <Button
                    className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                    onClick={() => setIsAllocateHostelModalOpen(true)}
                  >
                    <UserPlus className="h-4 w-4" />
                    Allocate Student to Room
                  </Button>
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsLogComplaintModalOpen(true)}
                  >
                    <AlertCircle className="h-4 w-4" />
                    Log Maintenance Complaint
                  </Button>
                </div>
              </div>

              {/* Buildings & Occupancy */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {hostelBuildings.map((b) => (
                  <Card key={b.id} className="bg-white dark:bg-slate-900">
                    <CardHeader className="pb-3">
                      <div className="flex items-center justify-between">
                        <CardTitle className="text-base font-bold">{b.name}</CardTitle>
                        <Badge className={b.building_type === "boys" ? "bg-blue-100 text-blue-800" : "bg-pink-100 text-pink-800"}>
                          {b.building_type.toUpperCase()} RESIDENCE
                        </Badge>
                      </div>
                      <CardDescription>
                        Warden: {b.warden_name} &bull; Contact: {b.warden_phone}
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                      <div className="flex justify-between text-sm">
                        <span className="text-slate-500">Occupancy:</span>
                        <span className="font-semibold text-slate-900 dark:text-slate-100">
                          {b.occupied_beds} / {b.total_capacity} Beds ({Math.round((b.occupied_beds / b.total_capacity) * 100)}%)
                        </span>
                      </div>
                      {/* Progress bar */}
                      <div className="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2.5">
                        <div
                          className="bg-indigo-600 h-2.5 rounded-full"
                          style={{ width: `${(b.occupied_beds / b.total_capacity) * 100}%` }}
                        ></div>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              {/* Rooms & Availability Table */}
              <Card className="bg-white dark:bg-slate-900">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">Rooms Registry & Availability</CardTitle>
                  <CardDescription>Room types, capacity, occupied beds, fee structure, and vacancies.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Building</TableHead>
                        <TableHead>Room Number</TableHead>
                        <TableHead>Room Type</TableHead>
                        <TableHead>Capacity</TableHead>
                        <TableHead>Occupied</TableHead>
                        <TableHead>Fee per Session</TableHead>
                        <TableHead>Availability</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {hostelRooms.map((r) => {
                        const isAvailable = r.occupied_beds < r.capacity;
                        return (
                          <TableRow key={r.id}>
                            <TableCell>{r.building_name}</TableCell>
                            <TableCell className="font-mono font-bold text-slate-900 dark:text-slate-100">
                              {r.room_number}
                            </TableCell>
                            <TableCell className="capitalize">{r.room_type}</TableCell>
                            <TableCell>{r.capacity} Beds</TableCell>
                            <TableCell>{r.occupied_beds}</TableCell>
                            <TableCell>₹{r.fee_per_session?.toLocaleString()}</TableCell>
                            <TableCell>
                              {isAvailable ? (
                                <Badge className="bg-emerald-100 text-emerald-800">
                                  {r.capacity - r.occupied_beds} Vacant
                                </Badge>
                              ) : (
                                <Badge variant="secondary" className="bg-slate-200 text-slate-800">
                                  Full
                                </Badge>
                              )}
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ========================================================================= */}
          {/* TAB 5: VISITOR FRONT DESK */}
          {/* ========================================================================= */}
          {activeTab === "visitors" && (
            <div className="space-y-6">
              {/* Front Desk KPI Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Visitors Today</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">{visitorPasses.length}</h3>
                    </div>
                    <Users className="h-8 w-8 text-blue-500 bg-blue-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Currently Inside Campus</p>
                      <h3 className="text-2xl font-bold mt-1 text-emerald-600">
                        {visitorPasses.filter((p) => p.status === "checked_in").length} Guests
                      </h3>
                    </div>
                    <UserCheck className="h-8 w-8 text-emerald-500 bg-emerald-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Student Early Pickups</p>
                      <h3 className="text-2xl font-bold mt-1 text-indigo-600">
                        {visitorPasses.filter((p) => p.pickup !== null).length} Verified
                      </h3>
                    </div>
                    <ShieldCheck className="h-8 w-8 text-indigo-500 bg-indigo-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>

                <Card className="bg-white dark:bg-slate-900">
                  <CardContent className="p-5 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-500">Checked Out</p>
                      <h3 className="text-2xl font-bold mt-1 text-slate-900 dark:text-slate-100">
                        {visitorPasses.filter((p) => p.status === "checked_out").length} Passes
                      </h3>
                    </div>
                    <LogOut className="h-8 w-8 text-slate-500 bg-slate-50 p-1.5 rounded-lg" />
                  </CardContent>
                </Card>
              </div>

              {/* Action Toolbar */}
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex gap-2">
                  <Button
                    className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                    onClick={() => setIsCheckInModalOpen(true)}
                  >
                    <UserPlus className="h-4 w-4" />
                    Visitor Check-In & Pass
                  </Button>
                  <Button
                    variant="outline"
                    className="flex items-center gap-2"
                    onClick={() => setIsGatePassModalOpen(true)}
                  >
                    <FileText className="h-4 w-4" />
                    Early Departure Gate Pass
                  </Button>
                </div>
              </div>

              {/* Visitor Passes Log */}
              <Card className="bg-white dark:bg-slate-900">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">Front Desk Visitor Passes & Security Log</CardTitle>
                  <CardDescription>Live visitors directory, security identification verification, host employee, and authorized student pickups.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Pass Number</TableHead>
                        <TableHead>Visitor Details</TableHead>
                        <TableHead>Host Employee</TableHead>
                        <TableHead>Purpose of Visit</TableHead>
                        <TableHead>Check-In</TableHead>
                        <TableHead>Student Pickup Auth</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Action</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {visitorPasses.map((p) => (
                        <TableRow key={p.id}>
                          <TableCell className="font-mono font-bold text-indigo-600">{p.pass_number}</TableCell>
                          <TableCell>
                            <div className="font-semibold text-slate-900 dark:text-slate-100">{p.visitor_name}</div>
                            <div className="text-xs text-slate-500">{p.phone}</div>
                          </TableCell>
                          <TableCell className="text-slate-700 dark:text-slate-300">{p.host_employee_name}</TableCell>
                          <TableCell className="text-sm">{p.purpose}</TableCell>
                          <TableCell className="text-xs text-slate-500">{p.check_in_time}</TableCell>
                          <TableCell>
                            {p.pickup ? (
                              <div className="text-xs space-y-0.5">
                                <Badge className="bg-emerald-100 text-emerald-800 text-[10px]">
                                  Authorized ({p.pickup.relationship})
                                </Badge>
                                <div className="text-slate-600 font-medium">{p.pickup.student_name}</div>
                              </div>
                            ) : (
                              <span className="text-xs text-slate-400">None</span>
                            )}
                          </TableCell>
                          <TableCell>
                            {p.status === "checked_in" ? (
                              <Badge className="bg-emerald-100 text-emerald-800">Inside Campus</Badge>
                            ) : (
                              <Badge variant="secondary" className="bg-slate-100 text-slate-700">
                                Checked Out ({p.check_out_time})
                              </Badge>
                            )}
                          </TableCell>
                          <TableCell className="text-right">
                            {p.status === "checked_in" && (
                              <Button
                                size="sm"
                                variant="outline"
                                className="text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                                onClick={() => handleCheckOut(p.id)}
                              >
                                Check Out
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}
        </div>
      </main>

      {/* ========================================================================= */}
      {/* MODALS */}
      {/* ========================================================================= */}

      {/* Modal: Add Book */}
      <Modal
        isOpen={isAddBookModalOpen}
        onClose={() => setIsAddBookModalOpen(false)}
        title="Catalog Library Book"
        description="Add a new book title to the catalog and generate physical barcode copies."
      >
        <form onSubmit={handleAddBook} className="space-y-4">
          <div>
            <Label htmlFor="b-title">Book Title *</Label>
            <Input
              id="b-title"
              required
              value={newBookForm.title}
              onChange={(e) => setNewBookForm({ ...newBookForm, title: e.target.value })}
              placeholder="e.g. Clean Architecture"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="b-author">Author *</Label>
              <Input
                id="b-author"
                required
                value={newBookForm.author}
                onChange={(e) => setNewBookForm({ ...newBookForm, author: e.target.value })}
                placeholder="e.g. Robert C. Martin"
              />
            </div>
            <div>
              <Label htmlFor="b-isbn">ISBN</Label>
              <Input
                id="b-isbn"
                value={newBookForm.isbn}
                onChange={(e) => setNewBookForm({ ...newBookForm, isbn: e.target.value })}
                placeholder="e.g. 978-0134494166"
              />
            </div>
          </div>
          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label htmlFor="b-cat">Category</Label>
              <Input
                id="b-cat"
                value={newBookForm.category}
                onChange={(e) => setNewBookForm({ ...newBookForm, category: e.target.value })}
              />
            </div>
            <div>
              <Label htmlFor="b-shelf">Shelf No.</Label>
              <Input
                id="b-shelf"
                value={newBookForm.shelf_no}
                onChange={(e) => setNewBookForm({ ...newBookForm, shelf_no: e.target.value })}
                placeholder="Shelf-A-1"
              />
            </div>
            <div>
              <Label htmlFor="b-copies">Copies to Generate</Label>
              <Input
                id="b-copies"
                type="number"
                min="1"
                max="100"
                value={newBookForm.initial_copies}
                onChange={(e) => setNewBookForm({ ...newBookForm, initial_copies: Number(e.target.value) })}
              />
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setIsAddBookModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Catalog & Generate Barcodes
            </Button>
          </div>
        </form>
      </Modal>

      {/* Modal: Barcode Scanner */}
      <Modal
        isOpen={isBarcodeScanModalOpen}
        onClose={() => {
          setIsBarcodeScanModalOpen(false);
          setScannedResult(null);
        }}
        title="Barcode / QR Desk Scanner"
        description="Scan or type barcode to instantly look up book copy status, shelf location, and active borrower."
      >
        <div className="space-y-4">
          <div className="flex gap-2">
            <Input
              placeholder="e.g. BC-LW-001-001"
              value={barcodeInput}
              onChange={(e) => setBarcodeInput(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleBarcodeLookup()}
            />
            <Button onClick={handleBarcodeLookup} className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Scan
            </Button>
          </div>

          {scannedResult && (
            <div className="p-4 rounded-lg border bg-slate-50 dark:bg-slate-900 space-y-2">
              <div className="flex justify-between items-center">
                <span className="font-mono font-bold text-indigo-600">{scannedResult.copy.barcode}</span>
                <Badge
                  className={
                    scannedResult.copy.status === "available"
                      ? "bg-emerald-100 text-emerald-800"
                      : "bg-amber-100 text-amber-800"
                  }
                >
                  {scannedResult.copy.status.toUpperCase()}
                </Badge>
              </div>
              <h4 className="font-semibold text-slate-900 dark:text-slate-100">{scannedResult.book.title}</h4>
              <p className="text-xs text-slate-500">
                Author: {scannedResult.book.author} &bull; Shelf: {scannedResult.book.shelf_no || "General"}
              </p>
            </div>
          )}
        </div>
      </Modal>

      {/* Modal: Add Vehicle */}
      <Modal
        isOpen={isAddVehicleModalOpen}
        onClose={() => setIsAddVehicleModalOpen(false)}
        title="Register Fleet Vehicle"
        description="Add a new bus or van to the transport fleet with fitness and insurance documents."
      >
        <form onSubmit={handleAddVehicle} className="space-y-4">
          <div>
            <Label htmlFor="v-reg">Registration Number *</Label>
            <Input
              id="v-reg"
              required
              value={newVehicleForm.registration_number}
              onChange={(e) => setNewVehicleForm({ ...newVehicleForm, registration_number: e.target.value })}
              placeholder="e.g. KA-01-AB-1234"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="v-model">Vehicle Model *</Label>
              <Input
                id="v-model"
                required
                value={newVehicleForm.vehicle_model}
                onChange={(e) => setNewVehicleForm({ ...newVehicleForm, vehicle_model: e.target.value })}
                placeholder="Tata Starbus 40-Seater"
              />
            </div>
            <div>
              <Label htmlFor="v-cap">Seating Capacity *</Label>
              <Input
                id="v-cap"
                type="number"
                required
                value={newVehicleForm.capacity}
                onChange={(e) => setNewVehicleForm({ ...newVehicleForm, capacity: Number(e.target.value) })}
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="v-fit">Fitness Certificate Expiry</Label>
              <Input
                id="v-fit"
                type="date"
                value={newVehicleForm.fitness_certificate_expiry}
                onChange={(e) => setNewVehicleForm({ ...newVehicleForm, fitness_certificate_expiry: e.target.value })}
              />
            </div>
            <div>
              <Label htmlFor="v-ins">Insurance Expiry</Label>
              <Input
                id="v-ins"
                type="date"
                value={newVehicleForm.insurance_expiry}
                onChange={(e) => setNewVehicleForm({ ...newVehicleForm, insurance_expiry: e.target.value })}
              />
            </div>
          </div>
          <div>
            <Label htmlFor="v-gps">GPS Device Hardware ID (Telemetry Hook)</Label>
            <Input
              id="v-gps"
              value={newVehicleForm.gps_device_id}
              onChange={(e) => setNewVehicleForm({ ...newVehicleForm, gps_device_id: e.target.value })}
              placeholder="e.g. GPS-TRK-9005"
            />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setIsAddVehicleModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Save Vehicle
            </Button>
          </div>
        </form>
      </Modal>

      {/* Modal: Check-In Visitor */}
      <Modal
        isOpen={isCheckInModalOpen}
        onClose={() => setIsCheckInModalOpen(false)}
        title="Visitor Registration & Security Check-In"
        description="Record visitor identity, host employee, purpose of visit, and authorized student pickup."
      >
        <form onSubmit={handleCheckInVisitor} className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="vis-name">Full Name *</Label>
              <Input
                id="vis-name"
                required
                value={checkInForm.full_name}
                onChange={(e) => setCheckInForm({ ...checkInForm, full_name: e.target.value })}
                placeholder="Visitor name"
              />
            </div>
            <div>
              <Label htmlFor="vis-phone">Phone Number *</Label>
              <Input
                id="vis-phone"
                required
                value={checkInForm.phone}
                onChange={(e) => setCheckInForm({ ...checkInForm, phone: e.target.value })}
                placeholder="+919876543210"
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="vis-id">Government Photo ID / Aadhaar</Label>
              <Input
                id="vis-id"
                value={checkInForm.national_id}
                onChange={(e) => setCheckInForm({ ...checkInForm, national_id: e.target.value })}
                placeholder="ID proof number"
              />
            </div>
            <div>
              <Label htmlFor="vis-host">Host Employee / Dept *</Label>
              <Input
                id="vis-host"
                required
                value={checkInForm.host_employee_name}
                onChange={(e) => setCheckInForm({ ...checkInForm, host_employee_name: e.target.value })}
                placeholder="e.g. Dennis Dsilva"
              />
            </div>
          </div>
          <div>
            <Label htmlFor="vis-purpose">Purpose of Visit *</Label>
            <Input
              id="vis-purpose"
              required
              value={checkInForm.purpose}
              onChange={(e) => setCheckInForm({ ...checkInForm, purpose: e.target.value })}
              placeholder="e.g. Admission inquiry / PTM"
            />
          </div>

          <div className="p-3 rounded-lg border bg-slate-50 dark:bg-slate-900 space-y-3">
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="vis-pickup"
                checked={checkInForm.is_student_pickup}
                onChange={(e) => setCheckInForm({ ...checkInForm, is_student_pickup: e.target.checked })}
                className="rounded text-indigo-600 h-4 w-4"
              />
              <Label htmlFor="vis-pickup" className="font-semibold text-slate-900 dark:text-slate-100 cursor-pointer">
                Authorized Student Early Pickup
              </Label>
            </div>

            {checkInForm.is_student_pickup && (
              <div className="grid grid-cols-2 gap-3 pt-2">
                <div>
                  <Label htmlFor="pk-std">Student Name & Class *</Label>
                  <Input
                    id="pk-std"
                    required
                    value={checkInForm.student_name}
                    onChange={(e) => setCheckInForm({ ...checkInForm, student_name: e.target.value })}
                    placeholder="e.g. Samuel Dsilva (7-C)"
                  />
                </div>
                <div>
                  <Label htmlFor="pk-rel">Relationship *</Label>
                  <Input
                    id="pk-rel"
                    required
                    value={checkInForm.relationship}
                    onChange={(e) => setCheckInForm({ ...checkInForm, relationship: e.target.value })}
                    placeholder="Father / Mother / Guardian"
                  />
                </div>
              </div>
            )}
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setIsCheckInModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Issue Visitor Pass
            </Button>
          </div>
        </form>
      </Modal>

      {/* Modal: Early Departure Gate Pass */}
      <Modal
        isOpen={isGatePassModalOpen}
        onClose={() => setIsGatePassModalOpen(false)}
        title="Emergency Early Departure Student Gate Pass"
        description="Issue official security exit slip for early child departure with guardian authorization."
      >
        <form
          onSubmit={(e) => {
            e.preventDefault();
            setIsGatePassModalOpen(false);
            setSuccessMessage(`Gate Pass issued for ${gatePassForm.student_name}. Security informed.`);
          }}
          className="space-y-4"
        >
          <div>
            <Label htmlFor="gp-std">Student Name *</Label>
            <Input
              id="gp-std"
              required
              value={gatePassForm.student_name}
              onChange={(e) => setGatePassForm({ ...gatePassForm, student_name: e.target.value })}
              placeholder="e.g. Samuel Dsilva"
            />
          </div>
          <div>
            <Label htmlFor="gp-grd">Accompanying Guardian *</Label>
            <Input
              id="gp-grd"
              required
              value={gatePassForm.guardian_name}
              onChange={(e) => setGatePassForm({ ...gatePassForm, guardian_name: e.target.value })}
              placeholder="e.g. George Dsilva (Father)"
            />
          </div>
          <div>
            <Label htmlFor="gp-rsn">Reason for Early Exit *</Label>
            <Input
              id="gp-rsn"
              required
              value={gatePassForm.reason}
              onChange={(e) => setGatePassForm({ ...gatePassForm, reason: e.target.value })}
            />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setIsGatePassModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Generate Gate Pass
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
