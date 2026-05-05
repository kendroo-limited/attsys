import { useEffect, useState } from "react";
import api from "./api";
import { getErrorMessage } from "./utils/errors";
import {
  Box,
  Button,
  Container,
  Paper,
  Typography,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Stack,
  useTheme,
  useMediaQuery,
  Card,
  CardContent,
  CardActions,
} from "@mui/material";
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
} from "@mui/icons-material";

export interface Department {
  id: number;
  name: string;
  designations?: { id: number; name: string }[];
}

export default function Departments() {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down("sm"));

  const [departments, setDepartments] = useState<Department[]>([]);

  // Modal States
  const [openForm, setOpenForm] = useState(false);
  const [currentDepartment, setCurrentDepartment] = useState<Partial<Department>>({});

  const [openDesignations, setOpenDesignations] = useState(false);
  const [selectedDeptForDesig, setSelectedDeptForDesig] = useState<Department | null>(null);
  const [newDesignationName, setNewDesignationName] = useState("");

  const fetchDepartments = async () => {
    try {
      const res = await api.get("/api/departments");
      setDepartments(res.data.departments || []);
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    const t = window.setTimeout(() => {
      void fetchDepartments();
    }, 0);
    return () => window.clearTimeout(t);
  }, []);

  const handleSave = async () => {
    try {
      const payload = {
        name: currentDepartment.name,
      };

      if (currentDepartment.id) {
        await api.post("/api/departments/update?id=" + currentDepartment.id, payload);
      } else {
        await api.post("/api/departments", payload);
      }
      setOpenForm(false);
      fetchDepartments();
    } catch (err: any) {
      alert(getErrorMessage(err, "Failed to save department"));
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm("Are you sure you want to delete this department?")) return;
    try {
      await api.post("/api/departments/delete?id=" + id, {});
      fetchDepartments();
    } catch (err: unknown) {
      alert(getErrorMessage(err, "Failed to delete department"));
    }
  };

  const openEdit = (dept: Department) => {
    setCurrentDepartment(dept);
    setOpenForm(true);
  };

  const openCreate = () => {
    setCurrentDepartment({
      name: "",
    });
    setOpenForm(true);
  };

  const openManageDesignations = (dept: Department) => {
    setSelectedDeptForDesig(dept);
    setNewDesignationName("");
    setOpenDesignations(true);
  };

  const handleAddDesignation = async () => {
    if (!selectedDeptForDesig || !newDesignationName.trim()) return;
    try {
      await api.post("/api/departments/designations", {
        department_id: selectedDeptForDesig.id,
        name: newDesignationName.trim()
      });
      setNewDesignationName("");
      fetchDepartments();
      
      // Update local state for immediate feedback
      setSelectedDeptForDesig(prev => {
        if (!prev) return prev;
        const newD = [...(prev.designations || []), { id: Date.now(), name: newDesignationName.trim() }];
        return { ...prev, designations: newD };
      });
    } catch (err: any) {
      alert(getErrorMessage(err, "Failed to add designation"));
    }
  };

  const handleDeleteDesignation = async (id: number) => {
    if (!window.confirm("Are you sure you want to delete this designation?")) return;
    try {
      await api.post("/api/departments/designations/delete?id=" + id, {});
      fetchDepartments();
      
      // Update local state
      setSelectedDeptForDesig(prev => {
        if (!prev) return prev;
        return { ...prev, designations: (prev.designations || []).filter(d => d.id !== id) };
      });
    } catch (err: unknown) {
      alert(getErrorMessage(err, "Failed to delete designation"));
    }
  };

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Stack
        direction="row"
        justifyContent="space-between"
        alignItems="center"
        mb={3}
      >
        <Box>
          <Typography variant="h4" fontWeight="bold">
            Departments
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Manage your organization's departments
          </Typography>
        </Box>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={openCreate}
        >
          New Department
        </Button>
      </Stack>

      {/* Desktop Table View */}
      {!isMobile && (
        <TableContainer component={Paper} elevation={0} variant="outlined">
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Department Name</TableCell>
                <TableCell align="right">Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {departments.length === 0 && (
                <TableRow>
                  <TableCell colSpan={2} align="center">
                    No departments found.
                  </TableCell>
                </TableRow>
              )}
              {departments.map((dept) => (
                <TableRow key={dept.id}>
                  <TableCell>
                    <Typography fontWeight="medium">{dept.name}</Typography>
                    <Typography variant="caption" color="text.secondary">
                      {dept.designations?.length || 0} Designations
                    </Typography>
                  </TableCell>
                  <TableCell align="right">
                    <Button 
                      size="small" 
                      variant="outlined" 
                      sx={{ mr: 1 }}
                      onClick={() => openManageDesignations(dept)}
                    >
                      Designations
                    </Button>
                    <IconButton size="small" onClick={() => openEdit(dept)}>
                      <EditIcon />
                    </IconButton>
                    <IconButton
                      size="small"
                      color="error"
                      onClick={() => handleDelete(dept.id)}
                    >
                      <DeleteIcon />
                    </IconButton>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      {/* Mobile Card View */}
      {isMobile && (
        <Stack spacing={2}>
          {departments.length === 0 && (
            <Typography align="center" color="text.secondary" mt={4}>
              No departments found.
            </Typography>
          )}
          {departments.map((dept) => (
            <Card key={dept.id} variant="outlined">
              <CardContent>
                <Typography variant="h6">{dept.name}</Typography>
                <Typography variant="caption" color="text.secondary">
                  {dept.designations?.length || 0} Designations
                </Typography>
              </CardContent>
              <CardActions>
                <Button
                  size="small"
                  onClick={() => openManageDesignations(dept)}
                >
                  Roles
                </Button>
                <Button
                  size="small"
                  startIcon={<EditIcon />}
                  onClick={() => openEdit(dept)}
                >
                  Edit
                </Button>
                <Button
                  size="small"
                  color="error"
                  onClick={() => handleDelete(dept.id)}
                >
                  Delete
                </Button>
              </CardActions>
            </Card>
          ))}
        </Stack>
      )}

      {/* Create/Edit Modal */}
      <Dialog
        open={openForm}
        onClose={() => setOpenForm(false)}
        maxWidth="sm"
        fullWidth
        fullScreen={isMobile}
        PaperProps={{ sx: { borderRadius: isMobile ? 0 : 3 } }}
      >
        <DialogTitle>
          {currentDepartment.id ? "Edit Department" : "Create Department"}
        </DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2} pt={1}>
            <TextField
              label="Department Name"
              fullWidth
              value={currentDepartment.name || ""}
              onChange={(e) =>
                setCurrentDepartment({ ...currentDepartment, name: e.target.value })
              }
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpenForm(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleSave} disabled={!currentDepartment.name}>
            Save
          </Button>
        </DialogActions>
      </Dialog>

      {/* Designations Modal */}
      <Dialog
        open={openDesignations}
        onClose={() => setOpenDesignations(false)}
        maxWidth="sm"
        fullWidth
        fullScreen={isMobile}
        PaperProps={{ sx: { borderRadius: isMobile ? 0 : 3 } }}
      >
        <DialogTitle>
          Manage Designations - {selectedDeptForDesig?.name}
        </DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2} pt={1} pb={2} direction="row">
            <TextField
              label="New Designation"
              size="small"
              fullWidth
              value={newDesignationName}
              onChange={(e) => setNewDesignationName(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  handleAddDesignation();
                }
              }}
            />
            <Button 
              variant="contained" 
              onClick={handleAddDesignation}
              disabled={!newDesignationName.trim()}
            >
              Add
            </Button>
          </Stack>
          
          <TableContainer variant="outlined" component={Paper} elevation={0}>
            <Table size="small">
              <TableBody>
                {(!selectedDeptForDesig?.designations || selectedDeptForDesig.designations.length === 0) && (
                  <TableRow>
                    <TableCell align="center" color="text.secondary">
                      No designations added yet.
                    </TableCell>
                  </TableRow>
                )}
                {selectedDeptForDesig?.designations?.map(desig => (
                  <TableRow key={desig.id}>
                    <TableCell>{desig.name}</TableCell>
                    <TableCell align="right" width={50}>
                      <IconButton size="small" color="error" onClick={() => handleDeleteDesignation(desig.id)}>
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpenDesignations(false)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Container>
  );
}
