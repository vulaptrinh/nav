// CashTrack Supabase Configuration
const SUPABASE_URL = "https://zphiunanffyehkwplqpq.supabase.co";
const SUPABASE_ANON_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InpwaGl1bmFuZmZ5ZWhrd3BscXBxIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTM1Nzg5ODUsImV4cCI6MjEwOTE1NDk4NX0.B0aESL0tiD0Shp1_pkMWyS6-VdmUbvhd-U6xfIbhJ3Y";

// Khởi tạo Supabase client
const supabaseClient = window.supabase ? window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY) : null;
