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
}

export default function Departments() {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down("sm"));

  const [departments, setDepartments] = useState<Department[]>([]);

  // Modal States
  const [openForm, setOpenForm] = useState(false);
  const [currentDepartment, setCurrentDepartment] = useState<Partial<Department>>({});

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
                  </TableCell>
                  <TableCell align="right">
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
              </CardContent>
              <CardActions>
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
    </Container>
  );
}
